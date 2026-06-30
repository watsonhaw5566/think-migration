<?php

declare(strict_types = 1);

// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------
namespace think\migration;

use Phinx\Seed\AbstractSeed;
use think\App;

class Seeder extends AbstractSeed
{
    /**
     * @return Factory
     */
    public function factory()
    {
        if (function_exists('app')) {
            return app(Factory::class);
        }

        $app = class_exists(App::class) ? App::getInstance() : null;
        if ($app !== null && method_exists($app, 'make')) {
            return $app->make(Factory::class);
        }

        throw new \RuntimeException('Unable to resolve Factory: application container not available.');
    }
}