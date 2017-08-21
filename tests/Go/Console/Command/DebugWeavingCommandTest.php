<?php
declare(strict_types=1);

namespace Go\Console\Command;

use Go\Functional\BaseFunctionalTest;

class DebugWeavingCommandTest extends BaseFunctionalTest
{
    public function testReportInconsistentWeaving()
    {
        $output = str_replace("\n", ' ', $this->execute('debug:weaving', null, false, 1));

        $this->assertRegexp('/.+InconsistentlyWeavedClass.php.+generated.+on.+second.+"warmup".+pass.+/', $output);
        $this->assertRegexp('/.+Main.php".+is.+consistently.+weaved.+/', $output);
        $this->assertContains('[ERROR] Weaving is unstable, there are 1 reported error(s).', $output);
    }

    protected function getConfigurationName(): string
    {
        return 'inconsistent_weaving';
    }
}
