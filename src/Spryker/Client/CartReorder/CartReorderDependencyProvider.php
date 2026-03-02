<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\CartReorder;

use Spryker\Client\CartReorder\Dependency\Client\CartReorderToQuoteClientBridge;
use Spryker\Client\CartReorder\Dependency\Client\CartReorderToZedRequestClientBridge;
use Spryker\Client\Kernel\AbstractDependencyProvider;
use Spryker\Client\Kernel\Container;

/**
 * @method \Spryker\Client\CartReorder\CartReorderConfig getConfig()
 */
class CartReorderDependencyProvider extends AbstractDependencyProvider
{
    /**
     * @var string
     */
    public const CLIENT_ZED_REQUEST = 'CLIENT_ZED_REQUEST';

    /**
     * @var string
     */
    public const CLIENT_QUOTE = 'CLIENT_QUOTE';

    /**
     * @var string
     */
    public const PLUGINS_CART_REORDER_QUOTE_PROVIDER_STRATEGY = 'PLUGINS_CART_REORDER_QUOTE_PROVIDER_STRATEGY';

    public function provideServiceLayerDependencies(Container $container): Container
    {
        $container = parent::provideServiceLayerDependencies($container);
        $container = $this->addZedRequestClient($container);
        $container = $this->addQuoteClient($container);
        $container = $this->addCartReorderQuoteProviderStrategyPlugins($container);

        return $container;
    }

    protected function addZedRequestClient(Container $container): Container
    {
        $container->set(static::CLIENT_ZED_REQUEST, function (Container $container) {
            return new CartReorderToZedRequestClientBridge($container->getLocator()->zedRequest()->client());
        });

        return $container;
    }

    protected function addQuoteClient(Container $container): Container
    {
        $container->set(static::CLIENT_QUOTE, function (Container $container) {
            return new CartReorderToQuoteClientBridge($container->getLocator()->quote()->client());
        });

        return $container;
    }

    protected function addCartReorderQuoteProviderStrategyPlugins(Container $container): Container
    {
        $container->set(static::PLUGINS_CART_REORDER_QUOTE_PROVIDER_STRATEGY, function () {
            return $this->getCartReorderQuoteProviderStrategyPlugins();
        });

        return $container;
    }

    /**
     * @return list<\Spryker\Client\CartReorderExtension\Dependency\Plugin\CartReorderQuoteProviderStrategyPluginInterface>
     */
    protected function getCartReorderQuoteProviderStrategyPlugins(): array
    {
        return [];
    }
}
