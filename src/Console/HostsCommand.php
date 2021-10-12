<?php
/* (c) Anton Medvedev <anton@medv.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Deployer\Console;

use Deployer\Deployer;
use Deployer\Host\Localhost;
use Deployer\Task\Context;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Helper\Table;

/**
 * @codeCoverageIgnore
 */
class HostsCommand extends Command
{
    /**
     * @var Deployer
     */
    private $deployer;

    /**
     * SshCommand constructor.
     * @param Deployer $deployer
     */
    public function __construct(Deployer $deployer)
    {
        parent::__construct('hosts');
        $this->setDescription('List hosts');
        $this->deployer = $deployer;
    }

    /**
     * Configures the command
     */
    protected function configure()
    {
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $hosts = $this->deployer->hosts->select(function ($host) {
            return !($host instanceof Localhost);
        });
        $table = new Table($output);
        $rows = [];
        foreach ($hosts as $host) {
            $id = '';
            if ($host->has('edge-id')) $id = $host->get('edge-id');
            $cluster = '';
            if ($host->has('cluster')) $cluster = $host->get('cluster');
            $stage = '';
            if ($host->has('stage')) $stage = $host->get('stage');
            $rows[] = [
                $id,
                $cluster,
                $stage,
                $host->getDescription(),
                implode(", ", $host->get('roles')),
            ];
        }
        usort($rows, function ($a, $b) {
            if ($a[1] != $b[1]) return strcmp($a[1], $a[1]);
            if ($a[2] != $b[2]) return strcmp($a[2], $a[2]);
            return strcmp(json_encode($a), json_encode($b));
        });
        $table
            ->setHeaders(['ID', 'Cluster', 'Stage', 'Host', 'Role'])
            ->setRows($rows);
        $table->render();

        return 0;
    }
}
