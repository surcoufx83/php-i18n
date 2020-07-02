<?php

/*
 * Fork this project on GitHub!
 * https://github.com/Philipp15b/php-i18n
 *
 * License: MIT
 */

namespace I18N;

class i18n {

  /**
   * Language file path
   * This is the path for the language files. You must use the '{LANGUAGE}' placeholder for the language or the script wont find any language files.
   *
   * @var string
   */
  protected $filePath = './lang/lang_{LANGUAGE}.yml';

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
  protected $forcedLang = null;

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
  protected $staticMap = [];


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
   * 4. HTTP_ACCEPT_LANGUAGE
   * 5. Language in $_COOKIE['lang']
   * 6. Fallback language
   *
   * @var array
   */
  protected $userLangs = [];
  protected $appliedLang = null;
  protected $isInitialized = false;
  protected $cacheFiles = [];


  /**
   * Constructor
   * The constructor sets all important settings. All params are optional, you can set the options via extra functions too.
   *
   * @param string [$filePath] This is the path for the language files. You must use the '{LANGUAGE}' placeholder for the language.
   * @param string [$cachePath] This is the path for all the cache files. Best is an empty directory with no other files in it. No placeholders.
   * @param string [$fallbackLang] This is the language which is used when there is no language file for all other user languages. It has the lowest priority.
   * @param string [$prefix] The class name of the compiled class that contains the translated texts. Defaults to 'L'.
   */
  public function __construct(
    ?string $filePath = null,
    ?string $cachePath = null,
    ?string $fallbackLang = null,
    ?string $prefix = null) {

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

  /**
   * Recursively compile an associative array to PHP code.
   *
   * @throws \InvalidArgumentException for invalid language configuration key names
   */
  protected function compile($config, $prefix = '') : string {
    $code = '';
    foreach ($config as $key => $value) {
      if (is_array($value)) {
        $code .= $this->compile($value, $prefix . $key . $this->sectionSeparator);
      } else {
        $fullName = $prefix . $key;
        if (!preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $fullName)) {
          throw new \InvalidArgumentException(__CLASS__ . ": Cannot compile translation key " . $fullName . " because it is not a valid PHP identifier.");
        }
        $value = str_replace(array_keys($this->staticMap), $this->staticMap, $value);
        $code .= 'const ' . $fullName . ' = \'' . addslashes($value) . "';\n";
      }
    }
    return $code;
  }

  public function getAppliedLang() : ?string {
    return $this->appliedLang;
  }

  public function getCachePath() : string {
    return $this->cachePath;
  }

  protected function getConfigFilename(string $langcode) : string {
    return str_replace('{LANGUAGE}', $langcode, $this->filePath);
  }

  public function getFallbackLang() : string {
    return $this->fallbackLang;
  }

  public function getFilePath() : string {
    return $this->filePath;
  }

  public function getForcedLang() : ?string {
    return $this->forcedLang;
  }

  public function getMergeFallback() : bool {
    return $this->mergeFallback;
  }

  public function getPrefix() : string {
    return $this->prefix;
  }

  public function getSectionSeparator() : string {
    return $this->sectionSeparator;
  }

  public function getStaticMap() : array {
    return $this->staticMap;
  }

  /**
   * getUserLangs()
   * Returns the user languages
   * Normally it returns an array like this:
   * 1. Forced language
   * 2. Language in $_GET['lang']
   * 3. Language in $_SESSION['lang']
   * 4. HTTP_ACCEPT_LANGUAGE
   * 5. Language in $_COOKIE['lang']
   * 6. Fallback language
   * Note: duplicate values are deleted.
   *
   * @return array with the user languages sorted by priority.
   */
  public function getUserLangs() : array {
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

    // 5th highest priority: COOKIE
    if (isset($_COOKIE['lang'])) {
      $userLangs[] = $_COOKIE['lang'];
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

  public function finishSetup() : bool {
    $this->initConfiguration();
    require_once $this->cacheFiles[$this->appliedLang];
    return true;
  }

  protected function initConfiguration() : void {
    $this->userLangs = $this->getUserLangs();
    $langFilePath = null;

    // search for language file
    $this->appliedLang = null;
    foreach ($this->userLangs as $priority => $langcode) {
      $langFilePath = $this->getConfigFilename($langcode);
      if (file_exists($langFilePath)) {
        $this->appliedLang = $langcode;
        break;
      }
    }
    if (is_null($this->appliedLang)) {
      throw new \RuntimeException('No language file was found.');
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
      $config = $this->loadConfiguration($langFilePath);
      if ($this->mergeFallback)
        $config = array_replace_recursive($this->loadConfiguration($this->getConfigFilename($this->fallbackLang)), $config);

      $compiled = "<?php class " . $this->prefix . " {\n"
      	. $this->compile($config)
      	. 'public static function __callStatic($string, $args) {' . "\n"
      	. '    return vsprintf(constant("self::" . $string), $args);'
      	. "\n}\n}\n"
      	. "function ".$this->prefix .'($string, $args=NULL) {'."\n"
      	. '    $return = constant("'.$this->prefix.'::".$string);'."\n"
      	. '    return $args !== NULL ? vsprintf($return,$args) : $return;'
      	. "\n}";

    	if(!is_dir($this->cachePath))
    		mkdir($this->cachePath, 0755, true);

      if (file_put_contents($cacheFilePath, $compiled) === FALSE) {
        throw new \Exception("Could not write cache file to path '" . $cacheFilePath . "'. Is it writable?");
      }
      chmod($cacheFilePath, 0755);

    }

    $this->cacheFiles[$this->appliedLang] = $cacheFilePath;
    $this->isInitialized = true;

  }

  public function isInitialized() : bool {
    return $this->isInitialized;
  }

  /**
   * This method is used to load the content of a language file. The method
   *   that is used for is determined by the file extension.
   *
   * @throws \InvalidArgumentException for unknown file extensions
   * @throws \Exception when no method for loading yml (or yaml) can be found.
   */
  protected function loadConfiguration($filename) : array {
    $ext = strtolower(substr(strrchr($filename, '.'), 1));
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
            throw new \Exception('No suitable YAML parsing methods available! Please install the PHP YAML extension or the spyc library.');
        break;
      case 'json':
        $config = json_decode(file_get_contents($filename), true);
        break;
      default:
        throw new \InvalidArgumentException($ext . " is not a valid extension!");
    }
    return $config;
  }

  public function setCachePath(string $cachePath) : i18n {
    $this->initConfiguration();
    $this->cachePath = $cachePath;
    return $this;
  }

  public function setFallbackLang(string $fallbackLang) : i18n {
    $this->initConfiguration();
    $this->fallbackLang = $fallbackLang;
    return $this;
  }

  public function setFilePath(string $filePath) : i18n {
    $this->initConfiguration();
    $this->filePath = $filePath;
    return $this;
  }

  public function setForcedLang(string $forcedLang) : i18n {
    $this->initConfiguration();
    $this->forcedLang = $forcedLang;
    return $this;
  }

  public function setMergeFallback(bool $mergeFallback) : i18n {
    $this->initConfiguration();
    $this->mergeFallback = $mergeFallback;
    return $this;
  }

  public function setPrefix(string $prefix) : i18n {
    $this->initConfiguration();
    $this->prefix = $prefix;
    return $this;
  }

  public function setSectionSeparator(string $sectionSeparator) : i18n {
    $this->initConfiguration();
    $this->sectionSeparator = $sectionSeparator;
    return $this;
  }

  public function setStaticMap(array $map) : i18n {
    $this->initConfiguration();
    $this->staticMap = $map;
    return $this;
  }

}
