<?php

	namespace I18N;

	// include i18n class and initialize it
	require_once 'i18n.class.php';

	$i18n = new i18n();
	// Parameters: language file path, cache dir, default language (all optional)

	// Switch to ini file and fallback language to German.
	$i18n->setFilePath('lang/lang_{LANGUAGE}.ini')
			 ->setFallbackLang('de');

	// init object: load language files, parse them if not cached, and so on.
	$i18n->init();

	// return the selected language
	echo "<p>Applied Language: ".$i18n->getAppliedLang()."</p>\r\n";

	// return the cache folder where compiled PHP files are stored
	echo "<p>Cache path: ".$i18n->getCachePath()."</p>\r\n";

	// Output some translated data
	echo "<p>A greeting: ".L::greeting."</p>\r\n";

	// Output translated data from a category
	echo "<p>Something other: ".L::category_somethingother."</p>\r\n";

?>
