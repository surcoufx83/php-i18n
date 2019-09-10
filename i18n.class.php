<?php

/*
 * Fork this project on GitHub!
 * https://github.com/Philipp15b/php-i18n
 *
 * License: MIT
 */

class i18n {

    /**
     * Language file path
     * This is the path for the language files. You must use the '{LANGUAGE}' placeholder for the language or the script wont find any language files.
     *
     * @var string
     */
    protected $filePath = './lang/lang_{LANGUAGE}.ini';

    /**
     * Cache file path
     * This is the path for all the cache files. Best is an empty directory with no other files in it.
     *
     * @var string
     */
    protected $cachePath = './langcache/';

    /**
     * Fallback language
     * This is the language which is used when there is no language file for all other user languages. It has the lowest priority.
     * Remember to create a language file for the fallback!!
     *
     * @var string
     */
    protected $fallbackLang = 'en';

    /**
     * Merge in fallback language
     * Whether to merge current language's strings with the strings of the fallback language ($fallbackLang).
     *
     * @var bool
     */
    protected $mergeFallback = false;

    /**
     * The class name of the compiled class that contains the translated texts.
     * @var string
     */
    protected $prefix = 'L';

    /**
     * Forced language
     * If you want to force a specific language define it here.
     *
     * @var string
     */
    protected $forcedLang = NULL;

    /**
     * This is the separator used if you use sections in your ini-file.
     * For example, if you have a string 'greeting' in a section 'welcomepage' you will can access it via 'L::welcomepage_greeting'.
     * If you changed it to 'ABC' you could access your string via 'L::welcomepageABCgreeting'
     *
     * @var string
     */
    protected $sectionSeparator = '_';

    /**
     * Static string replacements
     * This is an array of placeholders and their replacement values to be statically replaced. For example if you have
     * a string 'My {TYPE} string', and staticMap contains 'TYPE' => 'Favorite', then the resulting string will be 'My Favorite string'.
     *
     * @var array
     */
    protected $staticMap = array();


    /*
     * The following properties are only available after calling init().
     */

    /**
     * User languages
     * These are the languages the user uses.
     * Normally, if you use the getUserLangs-method this array will be filled in like this:
     * 1. Forced language
     * 2. Language in $_GET['lang']
     * 3. Language in $_SESSION['lang']
     * 4. Fallback language
     *
     * @var array
     */
    protected $userLangs = array();

    protected $appliedLang = NULL;
    protected $isInitialized = false;


    /**
     * Constructor
     * The constructor sets all important settings. All params are optional, you can set the options via extra functions too.
     *
     * @param string [$filePath] This is the path for the language files. You must use the '{LANGUAGE}' placeholder for the language.
     * @param string [$cachePath] This is the path for all the cache files. Best is an empty directory with no other files in it. No placeholders.
     * @param string [$fallbackLang] This is the language which is used when there is no language file for all other user languages. It has the lowest priority.
     * @param string [$prefix] The class name of the compiled class that contains the translated texts. Defaults to 'L'.
     */
    public function __construct($filePath = NULL, $cachePath = NULL, $fallbackLang = NULL, $prefix = NULL) {
        // Apply settings
        if ($filePath != NULL) {
            $this->filePath = $filePath;
        }

        if ($cachePath != NULL) {
            $this->cachePath = $cachePath;
        }

        if ($fallbackLang != NULL) {
            $this->fallbackLang = $fallbackLang;
        }

        if ($prefix != NULL) {
            $this->prefix = $prefix;
        }
    }

    public function init() {
        if ($this->isInitialized()) {
            throw new BadMethodCallException('This object from class ' . __CLASS__ . ' is already initialized. It is not possible to init one object twice!');
        }

        $this->isInitialized = true;

        $this->userLangs = $this->getUserLangs();

        $langFilePath = NULL;

        // search for language file
        $this->appliedLang = NULL;
        foreach ($this->userLangs as $priority => $langcode) {
            $langFilePath = $this->getConfigFilename($langcode);
            if (file_exists($langFilePath)) {
                $this->appliedLang = $langcode;
                break;
            }
        }
        if ($this->appliedLang == NULL) {
            throw new RuntimeException('No language file was found.');
        }

        // initialize and hash staticMap
        $smap_hash = NULL;
        if ($this->staticMap) {
            $smap_hctx = hash_init('md5');
            $new_staticMap = array();
            ksort($this->staticMap);
            foreach ($this->staticMap as $placeholder => $repl) {
                hash_update($smap_hctx, $placeholder . $repl);
                $new_staticMap['{' . $placeholder . '}'] = $repl;
            }
            $smap_hash = hash_final($smap_hctx);
            $this->staticMap = $new_staticMap;
            unset($new_staticMap, $smap_hctx);
        }

        $cacheFilePath = NULL;

        // search for cache file
        $cacheFilePath = $this->cachePath . '/php_i18n_' . md5_file(__FILE__) . '_' . ($smap_hash ? $smap_hash . '_' : '') . $this->prefix . '_' . $this->appliedLang . '.cache.php';

        // whether we need to create a new cache file
        $outdated = !file_exists($cacheFilePath) ||
            filemtime($cacheFilePath) < filemtime($langFilePath) || // the language config was updated
            ($this->mergeFallback && filemtime($cacheFilePath) < filemtime($this->getConfigFilename($this->fallbackLang))); // the fallback language config was updated

        if ($outdated) {
            $config = $this->load($langFilePath);
            if ($this->mergeFallback)
                $config = array_replace_recursive($this->load($this->getConfigFilename($this->fallbackLang)), $config);

            $compiled = "<?php class " . $this->prefix . " {\n"
            	. $this->compile($config)
            	. 'public static function __callStatic($string, $args) {' . "\n"
            	. '    return vsprintf(constant("self::" . $string), $args);'
            	. "\n}\n}\n"
            	. "function ".$this->prefix .'($string, $args=NULL) {'."\n"
            	. '    $return = constant("'.$this->prefix.'::".$string);'."\n"
            	. '    return $args !== NULL ? vsprintf($return,$args) : $return;'
            	. "\n}";

			if( ! is_dir($this->cachePath))
				mkdir($this->cachePath, 0755, true);

            if (file_put_contents($cacheFilePath, $compiled) === FALSE) {
                throw new Exception("Could not write cache file to path '" . $cacheFilePath . "'. Is it writable?");
            }
            chmod($cacheFilePath, 0755);

        }

        require_once $cacheFilePath;
    }

    public function isInitialized() {
        return $this->isInitialized;
    }

    public function getAppliedLang() {
        return $this->appliedLang;
    }

    public function getCachePath() {
        return $this->cachePath;
    }

    public function getFallbackLang() {
        return $this->fallbackLang;
    }

    public function setFilePath($filePath) {
        $this->fail_after_init();
        $this->filePath = $filePath;
    }

    public function setCachePath($cachePath) {
        $this->fail_after_init();
        $this->cachePath = $cachePath;
    }

    public function setFallbackLang($fallbackLang) {
        $this->fail_after_init();
        $this->fallbackLang = $fallbackLang;
    }

    public function setMergeFallback($mergeFallback) {
        $this->fail_after_init();
        $this->mergeFallback = $mergeFallback;
    }

    public function setPrefix($prefix) {
        $this->fail_after_init();
        $this->prefix = $prefix;
    }

    public function setForcedLang($forcedLang) {
        $this->fail_after_init();
        $this->forcedLang = $forcedLang;
    }

    public function setSectionSeparator($sectionSeparator) {
        $this->fail_after_init();
        $this->sectionSeparator = $sectionSeparator;
    }

    public function setStaticMap($map) {
        $this->fail_after_init();
        $this->staticMap = $map;
    }

    /**
     * @deprecated Use setSectionSeparator.
     */
    public function setSectionSeperator($sectionSeparator) {
        $this->setSectionSeparator($sectionSeparator);
    }

    /**
     * getUserLangs()
     * Returns the user languages
     * Normally it returns an array like this:
     * 1. Forced language
     * 2. Language in $_GET['lang']
     * 3. Language in $_SESSION['lang']
     * 4. HTTP_ACCEPT_LANGUAGE
     * 5. Fallback language
     * Note: duplicate values are deleted.
     *
     * @return array with the user languages sorted by priority.
     */
    public function getUserLangs() {
        $userLangs = array();

        // Highest priority: forced language
        if ($this->forcedLang != NULL) {
            $userLangs[] = $this->forcedLang;
        }

        // 2nd highest priority: GET parameter 'lang'
        if (isset($_GET['lang']) && is_string($_GET['lang'])) {
            $userLangs[] = $_GET['lang'];
        }

        // 3rd highest priority: SESSION parameter 'lang'
        if (isset($_SESSION['lang']) && is_string($_SESSION['lang'])) {
            $userLangs[] = $_SESSION['lang'];
        }

        // 4th highest priority: HTTP_ACCEPT_LANGUAGE
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            foreach (explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']) as $part) {
                $userLangs[] = strtolower(substr($part, 0, 2));
            }
        }

        // Lowest priority: fallback
        $userLangs[] = $this->fallbackLang;

        // remove duplicate elements
        $userLangs = array_unique($userLangs);

        // remove illegal userLangs
        // only allow a-z, A-Z and 0-9 and _ and -
        $userLangs = preg_grep('/^[a-zA-Z0-9_-]*$/', $userLangs);

        return $userLangs;
    }

    protected function getConfigFilename($langcode) {
        return str_replace('{LANGUAGE}', $langcode, $this->filePath);
    }

    protected function load($filename) {
        $ext = substr(strrchr($filename, '.'), 1);
        switch ($ext) {
            case 'properties':
            case 'ini':
                $config = parse_ini_file($filename, true);
                break;
            case 'yml':
            case 'yaml':
                if (function_exists('yaml_parse_file'))
                    $config = yaml_parse_file($filename);
                elseif (function_exists('spyc_load_file'))
                    $config = spyc_load_file($filename);
                else
                    throw new Exception('No suitable YAML parsing methods available! Please install the PHP YAML extension or the spyc library.');
                break;
            case 'json':
                $config = json_decode(file_get_contents($filename), true);
                break;
            default:
                throw new InvalidArgumentException($ext . " is not a valid extension!");
        }
        return $config;
    }

    /**
     * Recursively compile an associative array to PHP code.
     */
    protected function compile($config, $prefix = '') {
        $code = '';
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $code .= $this->compile($value, $prefix . $key . $this->sectionSeparator);
            } else {
                $fullName = $prefix . $key;
                if (!preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $fullName)) {
                    throw new InvalidArgumentException(__CLASS__ . ": Cannot compile translation key " . $fullName . " because it is not a valid PHP identifier.");
                }
                $value = str_replace(array_keys($this->staticMap), $this->staticMap, $value);
                $code .= 'const ' . $fullName . ' = \'' . addslashes($value) . "';\n";
            }
        }
        return $code;
    }

    protected function fail_after_init() {
        if ($this->isInitialized()) {
            throw new BadMethodCallException('This ' . __CLASS__ . ' object is already initalized, so you can not change any settings.');
        }
    }
}
