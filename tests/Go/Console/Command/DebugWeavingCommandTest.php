<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2017, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Console\Command;

use Go\Functional\BaseFunctionalTestCase;

class DebugWeavingCommandTest extends BaseFunctionalTestCase
{
    public function testReportInconsistentWeaving()
    {
        $output = str_replace("\n", ' ', $this->execute('debug:weaving', [], false, 1));

        $this->assertMatchesRegularExpression('/.+InconsistentlyWeavedClass.php.+generated.+on.+second.+"warmup".+pass.+/', $output);
        $this->assertMatchesRegularExpression('/.+Main.php".+is.+consistently.+weaved.+/', $output);
        $this->assertStringContainsString('[ERROR] Weaving is unstable, there are 1 reported error(s).', $output);
    }

    protected function getConfigurationName(): string
    {
        return 'inconsistent_weaving';
    }
}
