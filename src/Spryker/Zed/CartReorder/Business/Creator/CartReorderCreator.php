<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\CartReorder\Business\Creator;

use ArrayObject;
use Generated\Shared\Transfer\CartReorderRequestTransfer;
use Generated\Shared\Transfer\CartReorderResponseTransfer;
use Generated\Shared\Transfer\CartReorderTransfer;
use Generated\Shared\Transfer\ErrorTransfer;
use Generated\Shared\Transfer\ItemTransfer;
use Generated\Shared\Transfer\OrderTransfer;
use Generated\Shared\Transfer\QuoteTransfer;
use Spryker\Zed\CartReorder\Business\Adder\CartItemAdderInterface;
use Spryker\Zed\CartReorder\Business\Hydrator\ItemHydratorInterface;
use Spryker\Zed\CartReorder\Business\Reader\OrderReaderInterface;
use Spryker\Zed\CartReorder\Business\Resolver\PluginStackResolverInterface;
use Spryker\Zed\CartReorder\Business\Validator\CartReorderValidatorInterface;
use Spryker\Zed\Kernel\Persistence\EntityManager\TransactionTrait;

class CartReorderCreator implements CartReorderCreatorInterface
{
    use TransactionTrait;

    /**
     * @var string
     */
    protected const GLOSSARY_KEY_ORDER_NOT_FOUND = 'cart_reorder.validation.order_not_found';

    /**
     * @var string
     */
    protected const GLOSSARY_KEY_QUOTE_NOT_PROVIDED = 'cart_reorder.validation.quote_not_provided';

    /**
     * @var \Spryker\Zed\CartReorder\Business\Validator\CartReorderValidatorInterface
     */
    protected CartReorderValidatorInterface $cartReorderValidator;

    /**
     * @var \Spryker\Zed\CartReorder\Business\Reader\OrderReaderInterface
     */
    protected OrderReaderInterface $orderReader;

    /**
     * @var \Spryker\Zed\CartReorder\Business\Hydrator\ItemHydratorInterface
     */
    protected ItemHydratorInterface $itemHydrator;

    /**
     * @var \Spryker\Zed\CartReorder\Business\Adder\CartItemAdderInterface
     */
    protected CartItemAdderInterface $cartItemAdder;

    /**
     * @var \Spryker\Zed\CartReorder\Business\Resolver\PluginStackResolverInterface
     */
    protected PluginStackResolverInterface $pluginStackResolver;

    /**
     * @var list<\Spryker\Zed\CartReorderExtension\Dependency\Plugin\CartReorderQuoteProviderStrategyPluginInterface>
     */
    protected array $cartReorderQuoteProviderStrategyPlugins;

    /**
     * @var list<\Spryker\Zed\CartReorderExtension\Dependency\Plugin\CartReorderOrderItemFilterPluginInterface>
     */
    protected array $cartReorderOrderItemFilterPlugins;

    /**
     * @var list<\Spryker\Zed\CartReorderExtension\Dependency\Plugin\CartPreReorderPluginInterface>
     */
    protected array $cartPreReorderPlugins;

    /**
     * @var list<\Spryker\Zed\CartReorderExtension\Dependency\Plugin\CartPostReorderPluginInterface>|array<string, list<\Spryker\Zed\CartReorderExtension\Dependency\Plugin\CartPostReorderPluginInterface>>
     */
    protected array $cartPostReorderPlugins;

    /**
     * @param \Spryker\Zed\CartReorder\Business\Validator\CartReorderValidatorInterface $cartReorderValidator
     * @param \Spryker\Zed\CartReorder\Business\Reader\OrderReaderInterface $orderReader
     * @param \Spryker\Zed\CartReorder\Business\Hydrator\ItemHydratorInterface $itemHydrator
     * @param \Spryker\Zed\CartReorder\Business\Adder\CartItemAdderInterface $cartItemAdder
     * @param \Spryker\Zed\CartReorder\Business\Resolver\PluginStackResolverInterface $pluginStackResolver
     * @param list<\Spryker\Zed\CartReorderExtension\Dependency\Plugin\CartReorderQuoteProviderStrategyPluginInterface> $cartReorderQuoteProviderStrategyPlugins
     * @param list<\Spryker\Zed\CartReorderExtension\Dependency\Plugin\CartReorderOrderItemFilterPluginInterface> $cartReorderOrderItemFilterPlugins
     * @param list<\Spryker\Zed\CartReorderExtension\Dependency\Plugin\CartPreReorderPluginInterface> $cartPreReorderPlugins
     * @param list<\Spryker\Zed\CartReorderExtension\Dependency\Plugin\CartPostReorderPluginInterface>|array<string, list<\Spryker\Zed\CartReorderExtension\Dependency\Plugin\CartPostReorderPluginInterface>> $cartPostReorderPlugins
     */
    public function __construct(
        CartReorderValidatorInterface $cartReorderValidator,
        OrderReaderInterface $orderReader,
        ItemHydratorInterface $itemHydrator,
        CartItemAdderInterface $cartItemAdder,
        PluginStackResolverInterface $pluginStackResolver,
        array $cartReorderQuoteProviderStrategyPlugins,
        array $cartReorderOrderItemFilterPlugins,
        array $cartPreReorderPlugins,
        array $cartPostReorderPlugins
    ) {
        $this->cartReorderValidator = $cartReorderValidator;
        $this->orderReader = $orderReader;
        $this->itemHydrator = $itemHydrator;
        $this->cartItemAdder = $cartItemAdder;
        $this->pluginStackResolver = $pluginStackResolver;
        $this->cartReorderQuoteProviderStrategyPlugins = $cartReorderQuoteProviderStrategyPlugins;
        $this->cartReorderOrderItemFilterPlugins = $cartReorderOrderItemFilterPlugins;
        $this->cartPreReorderPlugins = $cartPreReorderPlugins;
        $this->cartPostReorderPlugins = $cartPostReorderPlugins;
    }

    /**
     * @param \Generated\Shared\Transfer\CartReorderRequestTransfer $cartReorderRequestTransfer
     *
     * @return \Generated\Shared\Transfer\CartReorderResponseTransfer
     */
    public function reorder(CartReorderRequestTransfer $cartReorderRequestTransfer): CartReorderResponseTransfer
    {
        $this->assertRequiredFields($cartReorderRequestTransfer);

        $orderTransfer = $this->orderReader->findCustomerOrder($cartReorderRequestTransfer);
        if (!$orderTransfer) {
            return (new CartReorderResponseTransfer())
                ->addError($this->createErrorTransfer(static::GLOSSARY_KEY_ORDER_NOT_FOUND));
        }

        $cartReorderRequestTransfer->setOrder($orderTransfer);

        $cartReorderResponseTransfer = $this->cartReorderValidator->validateRequest($cartReorderRequestTransfer);
        if ($cartReorderResponseTransfer->getErrors()->count()) {
            return $cartReorderResponseTransfer;
        }

        return $this->getTransactionHandler()->handleTransaction(function () use ($cartReorderRequestTransfer, $orderTransfer) {
            return $this->executeReorderTransaction($cartReorderRequestTransfer, $orderTransfer);
        });
    }

    /**
     * @param \Generated\Shared\Transfer\CartReorderRequestTransfer $cartReorderRequestTransfer
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     *
     * @return \Generated\Shared\Transfer\CartReorderResponseTransfer
     */
    protected function executeReorderTransaction(
        CartReorderRequestTransfer $cartReorderRequestTransfer,
        OrderTransfer $orderTransfer
    ): CartReorderResponseTransfer {
        $quoteTransfer = $this->executeCartReorderQuoteProviderStrategyPlugins($cartReorderRequestTransfer);
        if (!$quoteTransfer) {
            return (new CartReorderResponseTransfer())
                ->addError($this->createErrorTransfer(static::GLOSSARY_KEY_QUOTE_NOT_PROVIDED));
        }

        $cartReorderTransfer = (new CartReorderTransfer())
            ->setOrder($orderTransfer)
            ->setQuote($quoteTransfer);
        $cartReorderTransfer = $this->addOrderItemsToCartReorder(
            $this->filterOrderItems($cartReorderRequestTransfer),
            $cartReorderTransfer,
        );

        $cartReorderTransfer = $this->executeCartPreReorderPlugins($cartReorderRequestTransfer, $cartReorderTransfer);
        $cartReorderResponseTransfer = $this->cartReorderValidator->validate($cartReorderTransfer);
        if ($cartReorderResponseTransfer->getErrors()->count()) {
            return $cartReorderResponseTransfer;
        }

        $cartReorderTransfer = $this->itemHydrator->hydrate($cartReorderTransfer);
        $cartReorderResponseTransfer = $this->cartItemAdder->addToCart($cartReorderTransfer);

        if ($cartReorderResponseTransfer->getErrors()->count()) {
            return $cartReorderResponseTransfer;
        }

        $cartReorderTransfer = $this->executeCartPostReorderPlugins($cartReorderTransfer);

        return $cartReorderResponseTransfer->setQuote($cartReorderTransfer->getQuoteOrFail());
    }

    /**
     * @param \Generated\Shared\Transfer\CartReorderRequestTransfer $cartReorderRequestTransfer
     *
     * @return \ArrayObject<array-key, \Generated\Shared\Transfer\ItemTransfer>
     */
    protected function filterOrderItems(CartReorderRequestTransfer $cartReorderRequestTransfer): ArrayObject
    {
        $filteredOrderItems = $this->filterOrderItemsByIdSalesOrderItem($cartReorderRequestTransfer);

        return $this->executeCartReorderOrderItemFilterPlugins($filteredOrderItems, $cartReorderRequestTransfer);
    }

    /**
     * @param \ArrayObject<array-key, \Generated\Shared\Transfer\ItemTransfer> $filteredOrderItems
     * @param \Generated\Shared\Transfer\CartReorderRequestTransfer $cartReorderRequestTransfer
     *
     * @return \ArrayObject<array-key, \Generated\Shared\Transfer\ItemTransfer>
     */
    protected function executeCartReorderOrderItemFilterPlugins(
        ArrayObject $filteredOrderItems,
        CartReorderRequestTransfer $cartReorderRequestTransfer
    ): ArrayObject {
        foreach ($this->cartReorderOrderItemFilterPlugins as $cartReorderOrderItemFilterPlugin) {
            $filteredOrderItems = $cartReorderOrderItemFilterPlugin->filter($filteredOrderItems, $cartReorderRequestTransfer);
        }

        return $filteredOrderItems;
    }

    /**
     * @param \ArrayObject<array-key, \Generated\Shared\Transfer\ItemTransfer> $itemTransfers
     * @param \Generated\Shared\Transfer\CartReorderTransfer $cartReorderTransfer
     *
     * @return \Generated\Shared\Transfer\CartReorderTransfer
     */
    protected function addOrderItemsToCartReorder(
        ArrayObject $itemTransfers,
        CartReorderTransfer $cartReorderTransfer
    ): CartReorderTransfer {
        $orderItemTransfers = $cartReorderTransfer->getOrderItems();
        foreach ($itemTransfers as $index => $itemTransfer) {
            $orderItemTransfers->offsetSet($index, $this->cloneItemTransfer($itemTransfer));
        }

        return $cartReorderTransfer->setOrderItems($orderItemTransfers);
    }

    /**
     * @param \Generated\Shared\Transfer\CartReorderRequestTransfer $cartReorderRequestTransfer
     *
     * @return \Generated\Shared\Transfer\QuoteTransfer|null
     */
    protected function executeCartReorderQuoteProviderStrategyPlugins(
        CartReorderRequestTransfer $cartReorderRequestTransfer
    ): ?QuoteTransfer {
        foreach ($this->cartReorderQuoteProviderStrategyPlugins as $cartReorderQuoteProviderStrategyPlugin) {
            if ($cartReorderQuoteProviderStrategyPlugin->isApplicable($cartReorderRequestTransfer)) {
                return $cartReorderQuoteProviderStrategyPlugin->execute($cartReorderRequestTransfer);
            }
        }

        return $cartReorderRequestTransfer->getQuote();
    }

    /**
     * @param \Generated\Shared\Transfer\CartReorderRequestTransfer $cartReorderRequestTransfer
     * @param \Generated\Shared\Transfer\CartReorderTransfer $cartReorderTransfer
     *
     * @return \Generated\Shared\Transfer\CartReorderTransfer
     */
    protected function executeCartPreReorderPlugins(
        CartReorderRequestTransfer $cartReorderRequestTransfer,
        CartReorderTransfer $cartReorderTransfer
    ): CartReorderTransfer {
        foreach ($this->cartPreReorderPlugins as $cartPreReorderPlugin) {
            $cartReorderTransfer = $cartPreReorderPlugin->preReorder($cartReorderRequestTransfer, $cartReorderTransfer);
        }

        return $cartReorderTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\CartReorderTransfer $cartReorderTransfer
     *
     * @return \Generated\Shared\Transfer\CartReorderTransfer
     */
    protected function executeCartPostReorderPlugins(CartReorderTransfer $cartReorderTransfer): CartReorderTransfer
    {
        /** @var list<\Spryker\Zed\CartReorderExtension\Dependency\Plugin\CartPostReorderPluginInterface> $cartPostReorderPlugins */
        $cartPostReorderPlugins = $this->pluginStackResolver->resolvePluginStackByQuoteProcessFlowName(
            $cartReorderTransfer->getQuoteOrFail(),
            $this->cartPostReorderPlugins,
        );

        foreach ($cartPostReorderPlugins as $cartPostReorderPlugin) {
            $cartReorderTransfer = $cartPostReorderPlugin->postReorder($cartReorderTransfer);
        }

        return $cartReorderTransfer;
    }

    /**
     * @param string $message
     * @param array<string, int|string> $parameters
     *
     * @return \Generated\Shared\Transfer\ErrorTransfer
     */
    protected function createErrorTransfer(
        string $message,
        array $parameters = []
    ): ErrorTransfer {
        return (new ErrorTransfer())
            ->setMessage($message)
            ->setParameters($parameters);
    }

    /**
     * @param \Generated\Shared\Transfer\CartReorderRequestTransfer $cartReorderRequestTransfer
     *
     * @return void
     */
    protected function assertRequiredFields(CartReorderRequestTransfer $cartReorderRequestTransfer): void
    {
        $cartReorderRequestTransfer
            ->requireOrderReference()
            ->requireCustomerReference();
    }

    /**
     * @param \Generated\Shared\Transfer\CartReorderRequestTransfer $cartReorderRequestTransfer
     *
     * @return \ArrayObject<array-key, \Generated\Shared\Transfer\ItemTransfer>
     */
    protected function filterOrderItemsByIdSalesOrderItem(CartReorderRequestTransfer $cartReorderRequestTransfer): ArrayObject
    {
        if (!$cartReorderRequestTransfer->getSalesOrderItemIds()) {
            return $cartReorderRequestTransfer->getOrderOrFail()->getItems();
        }

        $filteredOrderItems = new ArrayObject();
        foreach ($cartReorderRequestTransfer->getOrderOrFail()->getItems() as $itemTransfer) {
            if (in_array($itemTransfer->getIdSalesOrderItemOrFail(), $cartReorderRequestTransfer->getSalesOrderItemIds())) {
                $filteredOrderItems->append($itemTransfer);
            }
        }

        return $filteredOrderItems;
    }

    /**
     * @param \Generated\Shared\Transfer\ItemTransfer $itemTransfer
     *
     * @return \Generated\Shared\Transfer\ItemTransfer
     */
    protected function cloneItemTransfer(ItemTransfer $itemTransfer): ItemTransfer
    {
        return (new ItemTransfer())->fromArray($itemTransfer->toArray());
    }
}
