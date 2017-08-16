<?php

namespace Go\Console\Command;

use Go\Functional\BaseFunctionalTest;

class DebugWeavingCommandTest extends BaseFunctionalTest
{
    public function setUp()
    {
        self::clearCache();
    }

    public function testReportInconsistentWeaving()
    {
        $output = self::exec('debug:weaving', '', 'inconsistent_weaving', false);

        $this->assertContains('aspect/_proxies/src/Application/InconsistentlyWeavedClass.php" is generated on second "warmup" pass.', $output);
        $this->assertContains('aspect/_proxies/src/Application/Main.php" is consistently weaved.', $output);
        $this->assertContains('[ERROR] Weaving is unstable, there are 1 reported error(s).', $output);
    }
}
