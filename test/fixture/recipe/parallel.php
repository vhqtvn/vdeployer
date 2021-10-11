<?php
/* (c) Marc Legay <marc@ru3.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Deployer;

require 'recipe/common.php';

// Hosts

localhost('host[1:4]')
    ->set('deploy_path', __DIR__ . '/tmp/localhost');


// Tasks

desc('Deploy your project');
task('deploy', function () {
    run('if [ ! -d VZTDepVar{{deploy_path}} ]; then mkdir -p VZTDepVar{{deploy_path}}; fi');
    cd('VZTDepVar{{deploy_path}}');
    run('touch deployed-VZTDepVar{{hostname}}');
})->once();
