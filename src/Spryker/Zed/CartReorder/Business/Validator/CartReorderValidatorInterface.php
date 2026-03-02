<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\CartReorder\Business\Validator;

use Generated\Shared\Transfer\CartReorderRequestTransfer;
use Generated\Shared\Transfer\CartReorderResponseTransfer;
use Generated\Shared\Transfer\CartReorderTransfer;

interface CartReorderValidatorInterface
{
    public function validateRequest(CartReorderRequestTransfer $cartReorderRequestTransfer): CartReorderResponseTransfer;

    public function validate(CartReorderTransfer $cartReorderTransfer): CartReorderResponseTransfer;
}
