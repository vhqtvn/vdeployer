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

/**
 * @codeCoverageIgnore
 */
class SshCommand extends Command
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
        parent::__construct('ssh');
        $this->setDescription('Connect to host through ssh');
        $this->deployer = $deployer;
    }

    /**
     * Configures the command
     */
    protected function configure()
    {
        $this->addArgument(
            'hostname',
            InputArgument::OPTIONAL,
            'Hostname'
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $hostname = $input->getArgument('hostname');
        if (!empty($hostname)) {
            $host = $this->deployer->hosts->get($hostname);
        } else {
            $hosts = $this->deployer->hosts->select(function ($host) {
                return !($host instanceof Localhost);
            });

            if (count($hosts) === 0) {
                $output->writeln('No remote hosts.');
                return; // Because there are no hosts.
            } elseif (count($hosts) === 1) {
                $host = array_shift($hosts);
            } else {
                $helper = $this->getHelper('question');
                $question = new Question(
                    'Select host: '
                );
                $hosts_str = [];
                foreach ($hosts as $ip => $host) $hosts_str[$ip] = $host->getDescription();
                $question->setAutocompleterCallback(function (string $userInput) use ($hosts_str) {
                    return array_map(fn ($x) => "$userInput : $x", array_filter($hosts_str, function ($host) use ($userInput) {
                        if ($userInput === '') return true;
                        $userInput = explode(' ', $userInput);
                        $userInput = array_map(fn ($x) => preg_quote($x, '#'), $userInput);
                        $userInput = '#' . implode('.*', $userInput) . "#";
                        return preg_match($userInput, $host) === 1;
                    }));
                });
                $question->setNormalizer(function ($value) {
                    return @explode(' ', explode(' : ', $value, 2)[1])[0];
                });

                $username = null;
                $hostname = $hostname_input = $helper->ask($input, $output, $question);
                if (!$hostname) throw new \Exception("No such host: $hostname");
                if (strpos($hostname, '@') !== false) {
                    [$username, $hostname] = explode('@', $hostname, 2);
                }

                $result_host = null;
                foreach ($this->deployer->hosts as $host => $chk_host) {
                    if (!is_null($username) && $username != $chk_host->getUser()) continue;
                    if ($hostname != $chk_host->getRealHostname()) continue;
                    $result_host = $chk_host;
                    break;
                }
                if (is_null($result_host)) throw new \Exception("No such host: $hostname_input");
                $host = $result_host;
            }
        }

        $shell_path = 'exec $SHELL -l';
        if ($host->has('shell_path')) {
            $shell_path = 'exec ' . $host->get('shell_path') . ' -l';
        }

        Context::push(new Context($host, $input, $output));
        $options = $host->getSshArguments();
        $deployPath = $host->get('deploy_path', '~');

        passthru("ssh -t $options " . escapeshellarg($host->getUser() . '@' . $host->getRealHostname()) . " '$shell_path'");
        return 0;
    }
}
