<?php
// Most Popular Dependencies For a Search Term on Packagist.

//@TODO: Use https://github.com/KnpLabs/packagist-api instead of `file_get_content`
//@TODO: Fetch https://packagist.org/packages/VENDOR/PROJECT.json instead of scraping github
//       and use the "require" section from the first ->package->versions (most likely dev-master)

outputHeader();
ob_start();

if (isset($argv[1])) {
    $sTerm = $argv[1];
} elseif (isset($_GET['q'])) {
    $sTerm = $_GET['q'];
} else {
    outputHelp();
}
if (isset($sTerm)) {
    // Get list of Repos
    $sUrl = 'https://packagist.org/search.json?q=' . $sTerm;
    output('Searching : ' . $sTerm);    

    $aList = getResults($sUrl);
    output('Found : ' . count($aList));

    $aSkipped = array();
    foreach ($aList as $t_sName => $t_sRepo) {
        // Grab Composer file from each Repo
        if (strpos($t_sRepo, 'github') !== false) {
            $sUrl = $t_sRepo;
            $sUrl = str_replace('git@github.com:', 'https://github.com/', $sUrl);
            $sUrl = str_replace('git://github.com/', 'https://github.com/', $sUrl);
            $sUrl = str_replace('http://github.com/', 'https://github.com/', $sUrl);

            if (substr($sUrl, -4) === '.git') {
                $sUrl = substr($sUrl, 0, -4);
            }
            $sUrl = str_replace('https://github.com/', 'https://raw.githubusercontent.com/', $sUrl);
            $sUrl =  $sUrl . '/HEAD/composer.json';
            
            $oComposer = getContentFrom($sUrl);
            
            // Grab "require" from each Composer file to list
            if ($oComposer === null) {
                $aSkipped[$t_sName] = $sUrl . ' does not exist! [404]'; 
                unset($aList[$t_sName]);
            } else if (isset($oComposer->require)) {            
                $aList[$t_sName] = $oComposer->require;
            } else {
                $aSkipped[$t_sName] = $sUrl . ' does not have a "require" section!'; 
                unset($aList[$t_sName]);
            }
        } else {
            $aSkipped[$t_sName] = $t_sRepo . ' is not a github repo. Unsupported!'; 
            unset($aList[$t_sName]);
        }
    }
    
    output('Skipped : ' . count($aSkipped));
    foreach ($aSkipped as $t_sName => $t_sReason) {
        output('    ----> ' . $t_sName . ' (' . $t_sReason .')');
    }
    
    // Tally totals
    $aTotals = array();
    foreach ($aList as $oRequirements) {
        $aRequirements = (array) $oRequirements;
        foreach ($aRequirements as $t_sName => $t_sVersion) {
            if (isset($aTotals[$t_sName]) === false) {
                $aTotals[$t_sName] = 0;
            }
            $aTotals[$t_sName]++;
        }
    }
    arsort($aTotals);
    
    // Output List
    output(var_export($aTotals, true), 'pre');
}

if (isCommandLine() === false) {
    output('window.clearInterval(window.intervalID);', 'script');
}

die;

//@TODO: handle ($oContent->results === 0)

function formatOutput($p_sMessage, $p_sTag) {
    $commandline = isCommandline();
    
    return sprintf(
        '%s%s%s%s', 
        $commandline?'':'<' . $p_sTag . '>', 
        $p_sMessage, 
        $commandline?'':'</' . $p_sTag . '>', 
        PHP_EOL
    );
}

function output($p_sMessage, $p_sTag='p') {
    echo formatOutput($p_sMessage, $p_sTag);
    
    if (isCommandline() === false) {
        echo str_pad(' ',4096) . PHP_EOL;
    }

    ob_flush();
    flush();

    usleep(5000);
}

function getLineBreak() {
    static $s;
    
    if ($s === null ) {
        $s = (isCommandLine()?PHP_EOL:'<br/>');
    }
    
    return $s;
};

function isCommandLine(){
    static $b;
    
    if ($b === null ) {
        $b = PHP_SAPI == 'cli';
    }
    
    return $b;
}
    
function outputHelp() {
    $bCommandline = isCommandLine();
    
    output('USAGE', 'p');
    output(''
        . ($bCommandline?'php ':'')
        . basename(__FILE__)
        . ($bCommandline?' SEARCHTERM':'?q='),
        'span'
    );
    outputForm();
}

function getContentFrom($p_sUrl) {
    output('Fetching : ' . $p_sUrl);
    
    $sJson = @file_get_contents($p_sUrl);
    if (empty($sJson)) {    
        output('    ----> 404');
        $oContent = null;
    } else {
        $oContent = json_decode($sJson);
    }
    
    return $oContent;
}

function getResults($p_sUrl) {
    $aList = array();
    
    $oContent = getContentFrom($p_sUrl);
        
    foreach($oContent->results as $t_oResult) {
        $aList[$t_oResult->name] = $t_oResult->repository;
    }

    if (isset($oContent->next)) {
        $aList = array_merge($aList, getResults($oContent->next));
    }

    return $aList;
}

function outputHeader() {
    if (isCommandLine()) {
        return;
    }
    echo <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        @import url(http://fonts.googleapis.com/css?family=Droid+Sans|Droid+Sans+Mono);

        body {
            background: black;
            color: white;
        }
        
        body, div, p, pre {
            margin: 0;
            padding: 3px 6px;
            font-family: 'Droid Sans Mono', monospace;
            font-size: 90%;
        }        
/*
        p:nth-child(odd) {
            background: #333;
        }
*/
        div {
            overflow: hidden;
            padding: 0;
        }
        input {
            color: white;
            background: black;
            font-size: 100%;
            font-weight: 150%;
            float: left;
            display: block;
            border: 1px solid wheat;
            font-family: 'Droid Sans Mono', monospace;
        }
        
        span, form, input {
            display: block;
            float: left;
        }
    </style>
    <script>
        window.intervalID = window.setInterval(function() {
          var elem = document.getElementsByTagName('body')[0];
          elem.scrollTop = elem.scrollHeight;
          console.log('scrolling...');
        }, 500);
    </script>

</head>
<body>
<div>
HTML;
}

function outputFooter() {
    if (isCommandLine()) {
        return;
    }
    
    echo <<<HTML
</body>
</div>
</html>

HTML;
}

function outputForm() {
    $sContent = '';
    if (isCommandLine() === false) {
        $sContent .= <<<HTML
<form action="">
    <input name="q" placeholder="SEARCHTERM" autofocus/>
</form>
HTML;
    }
    
    echo $sContent;    
}
/*EOF*/
