<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

use PHPUnit\Framework\TestSuite;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

/**
 * @author  Niels Theen <ntheen@databay.de>
 */
class ilServicesCertificateSuite extends TestSuite
{
    public static function suite(): ilServicesCertificateSuite
    {
        $suite = new self();

        $recursiveIteratorIterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(__DIR__, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        $regexIterator = new RegExIterator($recursiveIteratorIterator, '/(?<!Base)Test\.php$/');

        foreach ($regexIterator as $file) {
            /** @var SplFileInfo $file */
            require_once $file->getPathname();

            $className = preg_replace('/(.*?)(\.php)/', '$1', $file->getBasename());

            if (class_exists($className)) {
                $reflection = new ReflectionClass($className);
                if (
                    !$reflection->isAbstract() &&
                    !$reflection->isInterface() &&
                    $reflection->isSubclassOf(TestCase::class)) {
                    $suite->addTestSuite($className);
                }
            }
        }

        return $suite;
    }
}
