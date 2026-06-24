<?php
declare(strict_types=1);

namespace think\migration;

use think\console\Output;

class NullOutput extends Output
{
    public function __construct()
    {
        parent::__construct('nothing');
    }
}