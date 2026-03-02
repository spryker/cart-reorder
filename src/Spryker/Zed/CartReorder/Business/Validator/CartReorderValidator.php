<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\CartReorder\Business\Validator;

use Generated\Shared\Transfer\CartReorderRequestTransfer;
use Generated\Shared\Transfer\CartReorderResponseTransfer;
use Generated\Shared\Transfer\CartReorderTransfer;
use Spryker\Shared\Kernel\StrategyResolverInterface;

class CartReorderValidator implements CartReorderValidatorInterface
{
    /**
     * @param list<\Spryker\Zed\CartReorderExtension\Dependency\Plugin\CartReorderRequestValidatorPluginInterface> $cartReorderRequestValidatorPlugins
     * @param \Spryker\Shared\Kernel\StrategyResolverInterface<list<\Spryker\Zed\CartReorderExtension\Dependency\Plugin\CartReorderValidatorPluginInterface>> $cartReorderValidatorPluginStrategyResolver
     */
    public function __construct(
        protected array $cartReorderRequestValidatorPlugins,
        protected StrategyResolverInterface $cartReorderValidatorPluginStrategyResolver
    ) {
    }

    public function validateRequest(CartReorderRequestTransfer $cartReorderRequestTransfer): CartReorderResponseTransfer
    {
        return $this->executeCartReorderRequestValidatorPlugins(
            $cartReorderRequestTransfer,
            new CartReorderResponseTransfer(),
        );
    }

    public function validate(CartReorderTransfer $cartReorderTransfer): CartReorderResponseTransfer
    {
        return $this->executeCartReorderValidatorPlugins($cartReorderTransfer, new CartReorderResponseTransfer());
    }

    protected function executeCartReorderRequestValidatorPlugins(
        CartReorderRequestTransfer $cartReorderRequestTransfer,
        CartReorderResponseTransfer $cartReorderResponseTransfer
    ): CartReorderResponseTransfer {
        foreach ($this->cartReorderRequestValidatorPlugins as $cartReorderRequestValidatorPlugin) {
            $cartReorderResponseTransfer = $cartReorderRequestValidatorPlugin->validate(
                $cartReorderRequestTransfer,
                $cartReorderResponseTransfer,
            );
        }

        return $cartReorderResponseTransfer;
    }

    protected function executeCartReorderValidatorPlugins(
        CartReorderTransfer $cartReorderTransfer,
        CartReorderResponseTransfer $cartReorderResponseTransfer
    ): CartReorderResponseTransfer {
        $quoteProcessFlowName = $cartReorderTransfer->getQuoteOrFail()->getQuoteProcessFlow()?->getNameOrFail();
        $cartReorderValidatorPlugins = $this->cartReorderValidatorPluginStrategyResolver->get($quoteProcessFlowName);

        foreach ($cartReorderValidatorPlugins as $cartReorderValidatorPlugin) {
            $cartReorderResponseTransfer = $cartReorderValidatorPlugin->validate($cartReorderTransfer, $cartReorderResponseTransfer);
        }

        return $cartReorderResponseTransfer;
    }
}
