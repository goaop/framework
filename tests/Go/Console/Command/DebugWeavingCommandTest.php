<?php

namespace Go\Console\Command;

use Go\Functional\BaseFunctionalTest;

class DebugWeavingCommandTest extends BaseFunctionalTest
{
    public function testReportInconsistentWeaving()
    {
        $output = str_replace("\n", ' ', $this->execute('debug:weaving', null, false));

        $this->assertRegexp('/.+aspect\/_proxies\/src\/Application\/InconsistentlyWeavedClass.php.+generated on second "warmup" pass.+/', $output);
        $this->assertRegexp('/.+aspect\/_proxies\/src\/Application\/Main.php".+is consistently weaved.+/', $output);
        $this->assertContains('[ERROR] Weaving is unstable, there are 1 reported error(s).', $output);
    }

    protected function getConfigurationName()
    {
        return 'inconsistent_weaving';
    }
}
