<?php

declare(strict_types=1);

/*
 * This file is part of the Explicit Architecture POC,
 * which is created on top of the Symfony Demo application.
 *
 * (c) Herberto Graça <herberto.graca@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Acme\PhpExtension;

/**
 * This class is just an utility class that helps us to remove duplication from the tests
 * and that's why it can't be instantiated.
 */
abstract class AbstractStaticClass
{
    protected function __construct()
    {
        // All methods should be static, so no need to instantiate any of the subclasses
    }
}
