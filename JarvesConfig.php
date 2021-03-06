<?php

namespace Jarves;

use Jarves\Configuration\Model;
use Jarves\Configuration\SystemConfig;

class JarvesConfig
{
    /**
     * @var SystemConfig
     */
    protected $systemConfig;

    /**
     * @var string
     */
    private $rootDir;

    /**
     * @var string
     */
    private $environment;

    /**
     * @param string $rootDir
     * @param string $environment
     */
    public function __construct($rootDir, $environment)
    {
        $this->rootDir = $rootDir;
        $this->environment = $environment;
    }

    /**
     * @param bool $withCache
     *
     * @return SystemConfig
     */
    public function getSystemConfig($withCache = true)
    {
        if (null === $this->systemConfig) {

            $configFile = $this->rootDir . '/config/config.jarves.xml';
            $configEnvFile = $this->rootDir . '/config/config.jarves_' . $this->environment . '.xml';
            if (file_exists($configEnvFile)) {
                $configFile = $configEnvFile;
            }

            $cacheFile = $configFile . '.cache.php';
            $systemConfigCached = @file_get_contents($cacheFile);

            $cachedSum = 0;
            if ($systemConfigCached) {
                $cachedSum = substr($systemConfigCached, 0, 32);
                $systemConfigCached = substr($systemConfigCached, 33);
            }

            $systemConfigHash = file_exists($configFile) ? md5(filemtime($configFile)) : -1;

            if ($withCache && $systemConfigCached && $cachedSum === $systemConfigHash) {
                $this->systemConfig = @unserialize($systemConfigCached);
            }

            if (!$this->systemConfig) {
                $configXml = file_exists($configFile) ? file_get_contents($configFile) : [];
                $this->systemConfig = new SystemConfig($configXml);
                file_put_contents($cacheFile, $systemConfigHash . "\n" . serialize($this->systemConfig));
            }

//            if (!$this->systemConfig->getDatabase()) {
//                $database = $this->container->get('jarves.configuration.database');
//                $this->systemConfig->setDatabase($database);
//            }
        }

        return $this->systemConfig;
    }


}