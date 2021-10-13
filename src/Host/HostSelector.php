<?php
/* (c) Anton Medvedev <anton@medv.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Deployer\Host;

use Deployer\Exception\Exception;

class HostSelector
{
    /**
     * @var HostCollection|Host[]
     */
    private $hosts;

    /**
     * @var ?string
     */
    private $defaultCluster;

    /**
     * @var ?string
     */
    private $defaultStage;

    public function __construct(HostCollection $hosts, $cluster = null, $stage = null)
    {
        $this->hosts = $hosts;
        $this->defaultCluster = $cluster;
        $this->defaultStage = $stage;
    }

    public function get()
    {
        $hosts = [];

        foreach ($this->hosts as $host) {
            $hosts[$host->getHostname()] = $host;
        }

        return $hosts;
    }

    /**
     * @param ?string $cluster
     * @return Host[]
     * @throws Exception
     */
    public function getHosts($cluster = null)
    {
        $hosts = [];

        if (empty($cluster)) {
            $cluster = $this->defaultCluster;
        }

        foreach ($this->hosts as $host) {
            $include = true;
            if (empty($cluster)) $include = $include && (!$host->has('cluster') || $host->get('cluster') == '');
            else $include = $include && ($host->has('cluster') && $host->get('cluster') == $cluster);
            if ($include) {
                $hosts[$host->getHostname()] = $host;
            }
        }

        if (empty($hosts)) {
            if ($this->hosts->has($cluster)) {
                $hosts = [$cluster => $this->hosts->get($cluster)];
            } else {
                throw new Exception("Hostname or cluster `$cluster` was not found.");
            }
        }

        if (empty($hosts)) {
            if (count($this->hosts) === 0) {
                $hosts = ['localhost' => new Localhost()];
            } else {
                throw new Exception('You need to specify at least one host or cluster.');
            }
        }

        return $hosts;
    }

    /**
     * @param ?string $stage
     * @return Host[]
     * @throws Exception
     */
    public function getByStage($stage = null)
    {
        $hosts = [];

        if (empty($stage)) {
            $stage = $this->defaultStage;
        }

        foreach ($this->hosts as $host) {
            $include = true;
            if (empty($stage)) $include = $include && (!$host->has('stage') || $host->get('stage') == '');
            else $include = $include && ($host->has('stage') && $host->get('stage') == $stage);
            if ($include) {
                $hosts[$host->getHostname()] = $host;
            }
        }

        return new self(new HostCollection($hosts), cluster: $this->defaultCluster, stage: $this->defaultStage);
    }

    /**
     * @param $hostnames
     * @return Host[]
     */
    public function getByHostnames($hostnames)
    {
        $hostnames = Range::expand(array_map('trim', explode(',', $hostnames)));
        $hosts = array_map([$this->hosts, 'get'], $hostnames);
        return new self(new HostCollection($hosts), cluster: $this->defaultCluster, stage: $this->defaultStage);
    }

    /**
     * @param array|string $roles
     * @return Host[]
     */
    public function getByRoles($roles)
    {
        if (is_string($roles)) {
            $roles = array_map('trim', explode(',', $roles));
        }

        $hosts = [];
        foreach ($this->hosts as $host) {
            foreach ($host->get('roles', []) as $role) {
                if (in_array($role, $roles, true)) {
                    $hosts[$host->getHostname()] = $host;
                }
            }
        }

        return new self(new HostCollection($hosts), cluster: $this->defaultCluster, stage: $this->defaultStage);
    }
}
