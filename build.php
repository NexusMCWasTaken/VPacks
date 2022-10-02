<?php
/*
 * Copyright (c) 2022 Jan Sohn.
 * All rights reserved.
 * I don't want anyone to use my source code without permission.
 */
declare(strict_types=1);
set_time_limit(0);
ini_set("memory_limit", "-1");
$enable_version_suffix = isset(getopt("vs")["vs"]);
$secure = getenv("COMPUTERNAME") !== "JANPC" && true;
$from = getcwd() . DIRECTORY_SEPARATOR;
$description = yaml_parse_file($from . "plugin.yml");
$localServerPath = "C:/Users/" . getenv("USERNAME") . "/Desktop/pmmp" . (is_array($description["api"]) ? explode(".", $description["api"][0])[0] : (is_string($description["api"]) ? explode(".", $description["api"])[0] : "???")) . "/"; // string|null
$packages = [
	//EXAMPLE: "xxarox/web-server": ["paths" => ["src/","README.md"], "encode" => true]
	//"xxarox/waterdogpe-login-extra-data-fix" => ["paths" => ["src/"], "encode" => true]
];
$startTime = microtime(true);
$to = __DIR__ . DIRECTORY_SEPARATOR . "out" . DIRECTORY_SEPARATOR . $description["name"] . DIRECTORY_SEPARATOR;
$outputPath = $from . "out" . DIRECTORY_SEPARATOR . $description["name"] . ($enable_version_suffix ? "_v" . $description["version"] : "");
echo "[INFO]: Starting.." . PHP_EOL;
@mkdir($to, 0777, true);
cleanDirectory($to);

if (is_dir($from . "src")) {
	copyDirectory($from . "src", $to . "src/vezdehod/packs");
}
if (is_file($from . "LICENSE")) {
	file_put_contents($to . "LICENSE", file_get_contents($from . "LICENSE"));
}
if (is_file($from . "README.md")) {
	file_put_contents($to . "README.md", file_get_contents($from . "README.md"));
}


$excluded = [];
if (count($packages) > 0) {
	passthru("composer  --no-interaction dump-autoload -o", $result_code);
	if ($result_code != 0) throw new ErrorException("Error while updated autoloader.");
	foreach ($packages as $vendor => $obj) {
		if ($obj["encode"] ?? false) $excluded[] = $vendor . "/";
	}
}
$loader = include_once __DIR__ . "/vendor/autoload.php";
// include all packages
foreach ($packages as $vendor => $obj) {
	if (str_ends_with($vendor, "/")) $vendor = substr($vendor, 0, -1);
	foreach ($obj["paths"] as $paths) {
		foreach ($paths as $from2 => $to2) {
			if (is_dir($from . "vendor/$vendor")) copyDirectory($from . "vendor/$vendor/$from2", $to . $to2, [], str_ends_with("forms", $vendor));
			else throw new RuntimeException("Package '$vendor' is not installed.");
		}
	}
}
echo "[INFO]: Included " . count($packages) . " package" . (count($packages) == 1 ? "" : "s") . PHP_EOL;
//checkForErrors($from . "src/");
yaml_emit_file($to . "plugin.yml", $description);
if ($secure) {
	echo "[INFO]: Encoding plugin.." . PHP_EOL;
	if (getenv("USERNAME") !== false) {
		require_once "vendor/xxarox/plugin-security/src/Encoder.php";
		(new \xxAROX\PluginSecurity\Encoder($to, $excluded))->encode();
	}
	echo "[INFO]: Encoding done!" . PHP_EOL;
}
if (is_dir($to . "output/")) {
	$to = $to . "output/";
}
generatePhar($outputPath, $to);
if (!empty($localServerPath) && is_dir($localServerPath . "/plugins")) {
	echo "[INFO]: Compiling.." . PHP_EOL;
	generatePhar($localServerPath . "/plugins/" . $description["name"] . ($enable_version_suffix ? "_v" . $description["version"] : ""), $to);
	echo "[INFO]: Starting server.." . PHP_EOL;
	//startServer();
}
/**
 * Function copyDirectory
 * @param string $from
 * @param string $to
 * @param array $ignoredFiles
 * @return void
 */
function copyDirectory(string $from, string $to, array $ignoredFiles = [], $log = false): void{
	@mkdir($to, 0777, true);
	if ($log) var_dump($from);
	if (is_file($from)) {
		$files = [$from];
	} else {
		$ignoredFiles = array_map(fn(string $path) => str_replace("/", "\\", $path), $ignoredFiles);
		$files = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS), function (SplFileInfo $fileInfo, $key, $iterator) use ($from, $ignoredFiles, $log): bool{
			if ($log) var_dump($fileInfo, $key);
			if (!empty($ignoredFiles)) {
				$path = str_replace("/", "\\", $fileInfo->getPathname());
				foreach ($ignoredFiles as $ignoredFile) {
					if (str_starts_with($path, $ignoredFile)) {
						return false;
					}
				}
			}
			return true;
		}), RecursiveIteratorIterator::SELF_FIRST);
	}
	/** @var SplFileInfo $fileInfo */
	foreach ($files as $fileInfo) {
		$target = str_replace($from, $to, $fileInfo->getPathname());
		if ($fileInfo->isDir()) {
			@mkdir($target, 0777, true);
		} else {
			$contents = file_get_contents($fileInfo->getPathname());
			file_put_contents($target, $contents);
		}
	}
}

/**
 * Function cleanDirectory
 * @param string $directory
 * @return void
 */
function cleanDirectory(string $directory): void{
	$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
	/** @var SplFileInfo $fileInfo */
	foreach ($files as $fileInfo) {
		if ($fileInfo->isDir()) {
			rmdir($fileInfo->getPathname());
		} else {
			unlink($fileInfo->getPathname());
		}
	}
}

/**
 * Function generatePhar
 * @param string $outputPath
 * @param string $to
 * @return void
 */
function generatePhar(string $outputPath, string $to): void{
	echo "[INFO]: Building Phar in '$to'" . PHP_EOL;
	global $startTime;
	@unlink($outputPath . ".phar");
	$phar = new Phar($outputPath . ".phar");
	while (true) {
		try {
			$phar->buildFromDirectory($to);
			break;
		} catch (PharException $e) {
		}
		echo "Cannot access to file, file is used" . PHP_EOL;
		sleep(2);
	}
	$phar->buildFromDirectory($to);
	$phar->addFromString("C:/.lock", "This cause the devtools extract error");
	$phar->setSignatureAlgorithm(Phar::SHA512, "bdc70a4aeec173d80eae3f853019fda7270f32f78fc2590d7082a888b76365e923efcdcba6117a977c17a76f82c79a6dcbda1dfc097b6380839087a3d54dbb7f");
	$phar->compressFiles(Phar::GZ);
	echo "[INFO]: Built in " . round(microtime(true) - $startTime, 3) . " seconds! Output path: {$outputPath}.phar" . PHP_EOL;
}

function fetchResourcePack() {
	echo "[INFO]: Generating resource pack" . PHP_EOL;
	$location = __DIR__ . "/resources/Resource-Pack.zip";
	if (file_exists($location)) unlink($location);
	if (!file_exists(__DIR__ . "/out/.version")) file_put_contents(__DIR__ . "/out/.version", "0.0.0");
	$currentVersion = (string)file_get_contents(__DIR__ . "/out/.version");
	//$newVersion = implode(".", json_decode(file_get_contents(""), true)["header"]["version"] ?? ["null"]);

	if ($currentVersion !== $newVersion) {
		echo "[INFO]: New version found! Downloading..." . PHP_EOL;
		$curl = curl_init();
		curl_setopt_array($curl, [
			CURLOPT_URL => "https://api.github.com/repos/Cosmetic-X/main/releases/tags/{$newVersion}",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => [
				"User-Agent: Cosmetix Resource Pack Generator",
				"Accept: application/vnd.github.v3+json",
				"Authorization: token " . json_decode(file_get_contents(__DIR__ . "/composer.json")["config"]["github-oauth"]["github.com"], true)["header"]["token"]
			]
		]);
		$release = json_decode(curl_exec($curl), true);
		var_dump($release);
		file_put_contents(__DIR__ . "/out/.version", $newVersion);
		file_put_contents($location, $zipFileContents="");
	}
}


/**
 * Function startServer
 * @return void
 */
function startServer(): void{
	global $localServerPath;
	if (!is_dir($localServerPath)) return;
	if (!is_file($localServerPath . "/start.bat")) return;
	popen("start $localServerPath/start.bat", "r");
	exit;
}

function checkForErrors(string $directory): void{
	if (is_dir($directory)) {
		$scan = scandir($directory);
		unset($scan[0], $scan[1]); //unset . and ..
		foreach($scan as $file) {
			if (is_dir($directory."/".$file)) {
				checkForErrors($directory."/".$file);
			} else {
				if(str_contains($file, '.php')) {
					try {
						require_once($directory."/".$file);
					} catch (Throwable $throwable) {
						echo \pocketmine\utils\Terminal::$COLOR_RED . \pocketmine\utils\Terminal::$FORMAT_BOLD . "[ERROR]: " . $throwable->getMessage() . "\n";
					}
				}
			}
		}
	}
}