<?php header('Content-type: text/html; charset=utf-8'); ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>
<?php
//First and foremost, some input validation
$network = (isset($_GET['network']) ? htmlspecialchars($_GET['network']) : FALSE);
$channel = (isset($_GET['channel']) ? htmlspecialchars($_GET['channel']) : FALSE);
$date = ((isset($_GET['date']) and preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $_GET['date']) === 1) ? htmlspecialchars($_GET['date']) : FALSE);
$hideEvents = FALSE;
if (isset($_GET['hideevents']) and $_GET['hideevents'] === 'true') {
    $hideEvents = TRUE;
}
$darkMode = FALSE;
if (isset($_GET['darkmode']) and $_GET['darkmode'] === 'true') {
    $darkMode = TRUE;
}
//Nick colouring is on by default
$colourNicks = TRUE;
if (isset($_GET['colournicks']) and $_GET['colournicks'] === 'false') {
    $colourNicks = FALSE;
}

if ($network !== FALSE and $channel !== FALSE and $date !== FALSE) {
    echo 'Log for #'.$channel.' on '.$network.' from '.$date;
}
else echo 'Log Prettifier';
?>
</title>
<link rel="stylesheet" type="text/css" href="fonts/dejavu_sans_mono.css">
<link rel="stylesheet" type="text/css" href="log.css">
<meta name="robots" content="noindex">
</head>
<body <?php echo ($darkMode ? 'class="dark"' : ''); ?>>
<div id="page-wrapper">
<?php
function _optionToggle($varName, $curValue, $enableStr, $disableStr) {
    $linkUrl = $_SERVER['REQUEST_URI'];
    if (strpos($linkUrl, $varName) !== FALSE) {
        $linkUrl = preg_replace('/'.$varName.'=[^&#]+/',
                                $varName.'='.($curValue ? 'false' : 'true'),
                                $linkUrl);
    }
    else $linkUrl .= ($_SERVER['QUERY_STRING'] ? '&' : '?').$varName.'='.($curValue ? 'false' : 'true');
    return '<a class="optiontoggle" href="'.$linkUrl.'">'.($curValue ? $disableStr : $enableStr).'</a>';
}

function darkModeToggle($darkMode) {
    return _optionToggle('darkmode', $darkMode, 'Dark mode', 'Light mode');
}

function hideEventsToggle($hideEvents) {
    return _optionToggle('hideevents', $hideEvents, 'Hide events', 'Show events');
}

function colourNicksToggle($colourNicks) {
    return _optionToggle('colournicks', $colourNicks, 'Coloured nicks', 'Uncoloured nicks');
}

function printDirectory($queryString, $filePath, $toReplace, $descending) {
    $files = array_diff(scandir($filePath), array('..', '.'));

    if($descending) $files = array_reverse($files);

    echo '<ul class="dirlist">'."\r\n";
    foreach ($files as $file) {
        echo '<li><a href="/?'.$queryString.str_replace($toReplace,'',$file).'">'.$file.'</a></li>'."\r\n";
    }
    echo '</ul>'."\r\n";
}

function printHeader($menu, $title) {
    echo '<header id="header">'."\r\n";
    echo '<div id="menu">'.$menu.'</div>'."\r\n";
    echo '<div id="title">'.$title.'</div>'."\r\n";
    echo '<div class="jump"><a href="#footer">Jump To Bottom</a></div>'."\r\n";
    echo '</header>';
}

function buildUpLink($key, $text) {
    $upURL = preg_replace('/&?'.$key.'=[^&#]+/', '', $_SERVER['REQUEST_URI']);
    return '<a class="uplink" href="'.$upURL.'">'.$text.'</a>';
}

$startContent = '<div id="content-wrapper">'."\r\n";
$endContent = '</div>'."\r\n";

//If network, channel, or date are not set, list available options for those
$fileRoot = '/logpath';
if ($network === FALSE) {
    $menu = darkModeToggle($darkMode);
    $title = 'Logged networks';
    printHeader($menu, $title);

    echo $startContent;

    $queryString = 'darkmode='.($darkMode?'true':'false').'&network=';
    printDirectory($queryString, $fileRoot, '', FALSE);

    echo $endContent;
}
elseif ($channel === FALSE) {
    $menu = darkModeToggle($darkMode).' | '.buildUpLink('network', 'All Networks');
    $title = 'Logged channels on '.$network;
    printHeader($menu, $title);

    echo $startContent;

    $queryString = 'darkmode='.($darkMode?'true':'false').'&network='.$network.'&channel=';
    printDirectory($queryString, $fileRoot.'/'.$network, '#', FALSE);

    echo $endContent;
}
elseif ($date === FALSE) {
    $menu = darkModeToggle($darkMode).' | '.buildUpLink('channel', 'All Channels');
    $title = 'Logs for #'.$channel.' on '.$network;
    printHeader($menu, $title);

    echo $startContent;

    $queryString = 'darkmode='.($darkMode?'true':'false')
                 . '&network='.$network
                 . '&channel='.$channel
                 . '&date=';
    printDirectory($queryString, $fileRoot.'/'.$network.'/#'.$channel, '.log', TRUE);

    echo $endContent;
}
else {
    $filename = $fileRoot.'/'.$network.'/#'.$channel.'/'.$date.'.log';
    $lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); 

    if ($lines === FALSE) echo 'Error while trying to open log file.';
    elseif (count($lines) === 0) echo 'No lines found in the log file';
    else {
        unset($filename);

        $menu = hideEventsToggle($hideEvents)
              . ' | '.darkModeToggle($darkMode)
              . ' | '.colourNicksToggle($colourNicks)
              . ' | '.buildUpLink('date', 'All Logs');
        $title = '#'.$channel.' log for '.$date.' on '.$network;
        printHeader($menu, $title);

        echo $startContent;

        echo '<table class="log" id="log"><tr class="message"> <th class="time">TIME</th> <th class="user">NICK</th> <th class="text">MESSAGE</th></tr>'."\r\n";

        //Get the length of the first section of the first line, which is assumed to be the timestamp
        $timestampLength = strlen(explode(' ', $lines[0], 2)[0]);
        $suffixCharactersToRemove = array(')', '.');

        //Nick colour hashes
        $users = array();
        function hashNickDjb2($nick) {
            $hash = 5381;
            $length = strlen($nick);
            for ($i = 0; $i < $length; $i++) {
                if (in_array($nick[$i], ['_', '|', '['])) break;
                $hash ^= (($hash << 5) + ($hash >> 2)) + ord($nick[$i]);
            }
            return ($hash & 0xFFFFFFFF);
        }

        function wrapInNickColourSpan($nick, $users, $string) {
            $nickToHash = htmlspecialchars(trim(html_entity_decode($nick), '~&@%+'));
            if (array_key_exists($nickToHash, $users) === FALSE) {
                $users[$nickToHash] = hashNickDjb2($nickToHash);
            }
            $nickColour = $users[$nickToHash] % 32;
            return '<span class="nick'.$nickColour.'">'.$string.'</span>';
        }

        //Character constants used for adding colour to IRC messages
        $COLOUR_CHAR = '';
        $CANCEL_CHAR = '';
        $BOLD_CHAR = '';
        //An array to keep track of currently active text styles
        $openStyles = array('background' => '', 'foreground' => '', 'bold' => FALSE);
        //A function that'll get called for each message with $COLOUR_CHAR or $BOLD_CHAR in it, that styles the message accordingly
        function handleStyleCharacters($matches) {
            global $COLOUR_CHAR, $CANCEL_CHAR, $BOLD_CHAR, $openStyles;
            $replacementText = '';
            //If we're already doing something, anything, stop doing that.
            if ($openStyles['background'] !== '' or $openStyles['foreground'] !== '' or $openStyles['bold'] === TRUE) {
                $replacementText .= '</span>';
            }
            //Bold text. If we're already doing bold, stop it. If we're not, start
            if ($matches[0] === $BOLD_CHAR) {
                $openStyles['bold'] = !$openStyles['bold'];
            }
            //Colour char. If it's only that, clear colours. If it's followed by just one number, it's just foreground colour. Two numbers is fore- and background
            elseif (strpos($matches[0], $COLOUR_CHAR) !== FALSE) {
                //A single colour character. Clear all colours
                if ($matches[0] === $COLOUR_CHAR) {
                    $openStyles['background'] = '';
                    $openStyles['foreground'] = '';
                }
                //We've got at least a foreground colour
                else {
                    $openStyles['foreground'] = $matches[2];
                    //There's a background colour too (which includes the comma)
                    if (isset($matches[3])) {
                        $openStyles['background'] = ltrim($matches[3], ',');
                    }
                }
            }
            //We either reached a Cancel char, or the end of the sentence. Cleanup time!
            else {
                $openStyles['background'] = '';
                $openStyles['foreground'] = '';
                $openStyles['bold'] = FALSE;
            }
            
            //Now that all the parsing is done, construct the new <span>, if necessary
            if ($openStyles['background'] !== '' or $openStyles['foreground'] !== '' or $openStyles['bold'] === TRUE) {
                $replacementText .= '<span class="';
                if ($openStyles['background'] !== '') {
                    $replacementText .= 'bg'.$openStyles['background'].' ';
                }
                if ($openStyles['foreground'] !== '') {
                    $replacementText .= 'fg'.$openStyles['foreground'].' ';
                }
                if ($openStyles['bold'] === TRUE) {
                    $replacementText .= 'bold';
                }
                $replacementText = rtrim($replacementText).'">';
            }
            return $replacementText;
        }

        $linecount = count($lines);
        //Reverse the array so we can pop lines off the end, which is faster than getting them from the start
        $lines = array_reverse($lines);
        for ($i = 0; $i < $linecount; $i++) {
            $line = htmlspecialchars(array_pop($lines));
            if (strlen($line) > 0) {
                $lineSections = explode(' ', $line);
                $messageType = 'message';
                $nickType = 'user';
                //if there isn't both a < and a >, it's not a nick but an action or join/quit message. Change how that looks
                if (strpos($lineSections[1], '&lt;') === FALSE or strpos($lineSections[1], '&gt;') === FALSE) {
                    if ($lineSections[1] === '*') $messageType = 'action';
                    else $messageType = 'other';
                    $nickType = 'spacer';
                }
                //If we should hide events, just skip the echo-ing when it's a join or quit
                if ($hideEvents and $messageType === 'other') {
                    continue;
                }
                $message = substr($line, $timestampLength + strlen($lineSections[1])+2);
                //Turn URLs into hyperlinks
                if (strpos($message, 'http') !== FALSE or strpos($message, 'www') !== FALSE) {
                    preg_match_all("/(https?:\/\/\S+|www\.\S+\.\S+)/", $message, $regexResults);
                    foreach ($regexResults[0] as $urlText) {
                        //Remove some trailing characters that can mess up the url, like periods or parentheses
                        while (in_array(mb_substr($urlText, -1, 1), $suffixCharactersToRemove)) {
                            $urlText = mb_substr($urlText, 0, -1);
                        }
                        
                        //The display text can be different from the actual link it should be
                        $url = $urlText;
                        //Make sure the url actually starts with 'http' if there's no protocol specified
                        if (strpos($url, 'http://') !== 0 and strpos($url, 'https://') !== 0 and strpos($url, 'ftp://') !== 0) {
                            $url = 'http://'.$url;
                        }
                        
                        //Finally, actually change the text to a hyperlink
                        $message = str_replace($urlText, '<a href="'.$url.'" target="_blank">'.$urlText.'</a>', $message);
                    }
                }
                
                //Parse colour and bold codes, if necessary
                if (strpos($message, $COLOUR_CHAR) !== FALSE or strpos($message, $BOLD_CHAR) !== FALSE) {
                    $message = preg_replace_callback("/($COLOUR_CHAR(\d{1,2})(,\d{1,2})?|${COLOUR_CHAR}(?=[^\d])|$BOLD_CHAR|$CANCEL_CHAR|$)/", 'handleStyleCharacters', $message);
                }

                $nick = $lineSections[1];
                //Hash nicks and assign nick colours
                if ($nickType === 'user') {
                    $nick = htmlspecialchars(trim(html_entity_decode($lineSections[1]), '<>'));
                }
                if ($nickType === 'user' and $colourNicks === TRUE) {
                    $nick = wrapInNickColourSpan($nick, $users, $nick);
                }
                //Colour actions with their nick's colour
                if ($messageType === 'action' and $colourNicks === TRUE) {
                    $nickToColour = explode(" ", $message)[0];
                    $nick = wrapInNickColourSpan($nickToColour, $users, $nick);
                    $message = wrapInNickColourSpan($nickToColour, $users, $message);
                }

                echo '<tr class="'.$messageType.'">';
                echo '<td class="time"><a id="line'.$i.'" href="#line'.$i.'">'.$lineSections[0].'</a></td>';
                echo '<td class="'.$nickType.'">';
                if ($nickType === 'user') {
                    echo '&lt;'.$nick.'&gt;';
                }
                else {
                    echo $nick;
                }
                echo '</td> ';
                echo '<td class="text">'.$message.'</td>';
                echo "</tr>\r\n";
            }
        }
        echo "</table>\r\n";

        echo $endContent;
    }
}
?>
<footer id="footer">
<div class="jump"><a href="#header">Jump To Top</a></div>
<div class="center">Contribute to the log viewer on <a href="https://github.com/DesertBot/DesertBot-Log">GitHub</a></div>
</footer>
</div>
</body>
</html>
<?php
// vim: expandtab tabstop=4 shiftwidth=4
?>
