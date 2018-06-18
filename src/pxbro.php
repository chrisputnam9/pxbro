<?php
/**
 * PXBRO Console Interface
 */
class PXBRO extends Console_Abstract
{
    public const VERSION = 1;

    /**
     * Callable Methods
     */
    protected static $METHODS = [
        'update',
        'sync',
    ];

    // Name of script and directory to store config
    protected const SHORTNAME = 'pxbro';

    protected $__sources = ["Source definitions - set in config file", "array"];
    public $sources = [];

    protected $__cache = "Whether to use cached XML - for debugging/dev";
    public $cache = false;

    protected $__sync = ["Git SSH URL to sync output data", "string"];
    public $sync = '';

    protected $___update = [
        "Download XML for configured source",
        ["Source to update - defaults to first configured source", "string"],
    ];
    public function update($source=null)
    {
        $sources = array_keys($this->sources);
        $default_source = $sources[0];
        $source = $this->prepArg($source, $default_source);

        $source_config = $this->sources[$source];
        $url = $source_config['url'];

        $sourceObj = new PXBRO_Source($this, $source, $url, $this->cache);
        $sourceObj->readXML();
        $sourceObj->saveHTML('index');
    }

    protected $___sync = "Sync source files based on configured repository";
    public function sync()
    {
        if (empty($this->sync)) return;

        $this->output('Syncing...');

        if (substr($this->sync, 0, 4) == 'git@')
        {
            // Temporarily switch to config_dir
            $original_dir = getcwd();
            $output_dir = __DIR__ . DS . 'output';
            $this->log($output_dir);
            chdir($output_dir);

            // Set up git if not already done
            if (!is_dir($output_dir . DS . '.git'))
            {
                $this->log('Running commands to initialize git');
                $this->exec("git init");
                $this->exec("git remote add sync {$this->sync}");
            }

            // Pull
            $this->log('Pulling from remote (sync)');
            $this->exec("git pull sync master");

            // Push
            // $this->log('Committing and pushing to remote (sync)');
            $this->exec("git add . --all");
            $this->exec("git commit -m \"Automatic sync commit - {$this->stamp()}\"");
            $this->exec("git push sync master");

            // Switch back to original directory
            chdir($original_dir);
        }
    }

    /**
     * Get Config Dir
     */
    public function getConfigDir()
    {
        if (is_null($this->config_dir))
        {
            $this->config_dir = '.' . DS . '.' . static::SHORTNAME;
        }

        return $this->config_dir;
    }

    /**
     * Init config defaults, then call parent
     */
    public function initConfig()
    {
        $config_dir = $this->getConfigDir();

        // Config defaults
        $this->sources = [
            'example' => [
                'url' => 'https://www.w3schools.com/xml/simple.xml'
            ],
        ];

        parent::initConfig();
    }

}
PXBRO::run($argv);
?>
