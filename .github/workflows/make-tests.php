<?php
// Datenstrom Yellow, https://github.com/datenstrom/yellow

if (PHP_SAPI!="cli") {
    echo "ERROR making test environment: Please run at the command line!\n";
} else {
    if (!is_dir("tests")) {
        echo "Making test environment...\n";
        $extensions = $errors = 0;
        mkdir("tests/system/extensions", 0777, true);
        copy("yellow.php", "tests/yellow.php");
        copy("system/extensions/core.php", "tests/system/extensions/core.php");
        copy("system/extensions/update.php", "tests/system/extensions/update.php");
        $fileData = date("Y-m-d H:i:s")." info Make test environment for Datenstrom Yellow\n";
        file_put_contents("tests/system/extensions/yellow-website.log", $fileData);
        $fileData = "# Datenstrom Yellow system settings\n\nUpdateCurrentRelease: latest\nGenerateStaticUrl: http://localhost:8000/";
        file_put_contents("tests/system/extensions/yellow-system.ini", $fileData);
        $fileData = file_get_contents("system/extensions/update-latest.ini");
        $curlHandle = curl_init();
        preg_match_all("/DownloadUrl\s*:\s*(.*?)\s*[\r\n]+/i", $fileData, $urls);
        foreach ($urls[1] as $url) {
            $downloadUrl = $url;
            if (preg_match("#^https://github.com/annaesvensson/yellow-core/#", $url)) {
               ++$extensions; continue;
            }
            if (preg_match("#^https://github.com/annaesvensson/yellow-update/#", $url)) {
               ++$extensions; continue;
            }
            if (preg_match("#^https://github.com/(.+)/archive/refs/heads/main.zip$#", $url, $matches)) {
                $downloadUrl = "https://codeload.github.com/".$matches[1]."/zip/refs/heads/main";
            }
            if (preg_match("#^https://github.com/(.+)/raw/main/(.+)$#", $url, $matches)) {
                $downloadUrl = "https://raw.githubusercontent.com/".$matches[1]."/main/".$matches[2];
            }
            curl_setopt($curlHandle, CURLOPT_URL, $downloadUrl);
            curl_setopt($curlHandle, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; MakeTests/0.8.1; SoftwareTester)");
            curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 30);
            $rawData = curl_exec($curlHandle);
            $statusCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
            if ($statusCode==200) {
                ++$extensions;
                $fileName = "tests/system/extensions/download-$extensions.zip";
                file_put_contents($fileName, $rawData);
            } else {
                ++$errors;
                echo "ERROR downloading $url, status $statusCode!\n";
            }
        }
        curl_close($curlHandle);
        exec("cd tests; php yellow.php update; php yellow.php update", $outputLines, $returnStatus);
        if ($returnStatus!=0) {
            ++$errors;
            foreach ($outputLines as $line) echo "$line\n";
        }
        file_put_contents("tests/content/contact/page.md", "exclude\n");   //TODO: remove later, exclude contact page for now
        file_put_contents("tests/content/search/page.md", "exclude\n");    //TODO: remove later, exclude search page for now
        echo "Test environment: $extensions extension".($extensions!=1 ? "s" : "");
        echo ", $errors error".($errors!=1 ? "s" : "")."\n";
        exit($errors==0 ? 0 : 1);
    }
}
