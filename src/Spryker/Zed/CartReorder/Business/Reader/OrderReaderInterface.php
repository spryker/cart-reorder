<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\CartReorder\Business\Reader;

use Generated\Shared\Transfer\CartReorderRequestTransfer;
use Generated\Shared\Transfer\OrderTransfer;

interface OrderReaderInterface
{
    public function findCustomerOrder(CartReorderRequestTransfer $cartReorderRequestTransfer): ?OrderTransfer;
}
