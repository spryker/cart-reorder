<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\CartReorder;

use Spryker\Client\CartReorder\Creator\CartReorderCreator;
use Spryker\Client\CartReorder\Creator\CartReorderCreatorInterface;
use Spryker\Client\CartReorder\Dependency\Client\CartReorderToQuoteClientInterface;
use Spryker\Client\CartReorder\Dependency\Client\CartReorderToZedRequestClientInterface;
use Spryker\Client\CartReorder\Zed\CartReorderStub;
use Spryker\Client\CartReorder\Zed\CartReorderStubInterface;
use Spryker\Client\Kernel\AbstractFactory;

/**
 * @method \Spryker\Client\CartReorder\CartReorderConfig getConfig()
 */
class CartReorderFactory extends AbstractFactory
{
    public function createCartReorderCreator(): CartReorderCreatorInterface
    {
        return new CartReorderCreator(
            $this->createCartReorderStub(),
            $this->getQuoteClient(),
            $this->getCartReorderQuoteProviderStrategyPlugins(),
        );
    }

    public function createCartReorderStub(): CartReorderStubInterface
    {
        return new CartReorderStub(
            $this->getZedRequestClient(),
        );
    }

    public function getQuoteClient(): CartReorderToQuoteClientInterface
    {
        return $this->getProvidedDependency(CartReorderDependencyProvider::CLIENT_QUOTE);
    }

    public function getZedRequestClient(): CartReorderToZedRequestClientInterface
    {
        return $this->getProvidedDependency(CartReorderDependencyProvider::CLIENT_ZED_REQUEST);
    }

    /**
     * @return list<\Spryker\Client\CartReorderExtension\Dependency\Plugin\CartReorderQuoteProviderStrategyPluginInterface>
     */
    public function getCartReorderQuoteProviderStrategyPlugins(): array
    {
        return $this->getProvidedDependency(CartReorderDependencyProvider::PLUGINS_CART_REORDER_QUOTE_PROVIDER_STRATEGY);
    }
}
