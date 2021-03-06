<?php
/**
 * This file is a part of the CIDRAM package.
 * Homepage: https://cidram.github.io/
 *
 * CIDRAM COPYRIGHT 2016 and beyond by Caleb Mazalevskis (Maikuolan).
 *
 * License: GNU/GPLv2
 * @see LICENSE.txt
 *
 * This file: Functions file (last modified: 2017.10.09).
 */

/**
 * Extends compatibility with CIDRAM to PHP 5.4.x by introducing some simple
 * polyfills for functions introduced with newer versions of PHP.
 */
if (substr(PHP_VERSION, 0, 4) === '5.4.') {
    require $CIDRAM['Vault'] . 'php5.4.x.php';
}

/**
 * Reads and returns the contents of files.
 *
 * @param string $File Path and filename of the file to read.
 * @return string|bool Content of the file returned by the function (or false
 *      on failure).
 */
$CIDRAM['ReadFile'] = function ($File) {
    if (!is_file($File) || !is_readable($File)) {
        return false;
    }
    /**
     * $Blocksize represents the size of each block to be read from the target
     * file. 131072 = 128KB. Decreasing this value will increase stability but
     * decrease performance, whereas increasing this value will increase
     * performance but decrease stability.
     */
    $Blocksize = 131072;
    $Filesize = filesize($File);
    $Size = ($Filesize && $Blocksize) ? ceil($Filesize / $Blocksize) : 0;
    $Data = '';
    if ($Size > 0) {
        $Handle = fopen($File, 'rb');
        $r = 0;
        while ($r < $Size) {
            $Data .= fread($Handle, $Blocksize);
            $r++;
        }
        fclose($Handle);
    }
    return $Data ?: false;
};

/**
 * Replaces encapsulated substrings within an input string with the value of
 * elements within an input array, whose keys correspond to the substrings.
 * Accepts two input parameters: An input array (1), and an input string (2).
 *
 * @param array $Needle The input array (the needle[/s]).
 * @param string $Haystack The input string (the haystack).
 * @return string The resultant string.
 */
$CIDRAM['ParseVars'] = function ($Needle, $Haystack) {
    if (!is_array($Needle) || empty($Haystack)) {
        return '';
    }
    array_walk($Needle, function($Value, $Key) use (&$Haystack) {
        $Haystack = str_replace('{' . $Key . '}', $Value, $Haystack);
    });
    return $Haystack;
};

/**
 * Fetches instructions from the `ignore.dat` file.
 *
 * @return bool Which sections should be ignored by CIDRAM.
 */
$CIDRAM['FetchIgnores'] = function () use (&$CIDRAM) {
    $IgnoreMe = array();
    $IgnoreFile = $CIDRAM['ReadFile']($CIDRAM['Vault'] . 'ignore.dat');
    if (strpos($IgnoreFile, "\r")) {
        $IgnoreFile =
            (strpos($IgnoreFile, "\r\n")) ?
            str_replace("\r", '', $IgnoreFile) :
            str_replace("\r", "\n", $IgnoreFile);
    }
    $IgnoreFile = "\n" . $IgnoreFile . "\n";
    $PosB = -1;
    while (true) {
        $PosA = strpos($IgnoreFile, "\nIgnore ", ($PosB + 1));
        if ($PosA === false) {
            break;
        }
        $PosA += 8;
        if (!$PosB = strpos($IgnoreFile, "\n", $PosA)) {
            break;
        }
        $Tag = substr($IgnoreFile, $PosA, ($PosB - $PosA));
        if (strlen($Tag)) {
            $IgnoreMe[$Tag] = true;
        }
    }
    return $IgnoreMe;
};

/**
 * Tests whether $Addr is an IPv4 address, and if it is, expands its potential
 * factors (i.e., constructs an array containing the CIDRs that contain $Addr).
 * Returns false if $Addr is *not* an IPv4 address, and otherwise, returns the
 * contructed array.
 *
 * @param string $Addr Refer to the description above.
 * @param bool $ValidateOnly If true, just checks if the IP is valid only.
 * @param int $FactorLimit Maximum number of CIDRs to return (default: 32).
 * @return bool|array Refer to the description above.
 */
$CIDRAM['ExpandIPv4'] = function ($Addr, $ValidateOnly = false, $FactorLimit = 32) {
    if (!preg_match(
        '/^([01]?[0-9]{1,2}|2[0-4][0-9]|25[0-5])\.([01]?[0-9]{1,2}|2[0-4][0-' .
        '9]|25[0-5])\.([01]?[0-9]{1,2}|2[0-4][0-9]|25[0-5])\.([01]?[0-9]{1,2' .
        '}|2[0-4][0-9]|25[0-5])$/i',
    $Addr, $Octets)) {
        return false;
    }
    if ($ValidateOnly) {
        return true;
    }
    $CIDRs = array();
    $Base = array(0, 0, 0, 0);
    for ($Cycle = 0; $Cycle < 4; $Cycle++) {
        for ($Size = 128, $Step = 0; $Step < 8; $Step++, $Size /= 2) {
            $CIDR = $Step + ($Cycle * 8);
            $Base[$Cycle] = floor($Octets[$Cycle + 1] / $Size) * $Size;
            $CIDRs[$CIDR] = $Base[0] . '.' . $Base[1] . '.' . $Base[2] . '.' . $Base[3] . '/' . ($CIDR + 1);
            if ($CIDR >= $FactorLimit) {
                break 2;
            }
        }
    }
    return $CIDRs;
};

/**
 * Tests whether $Addr is an IPv6 address, and if it is, expands its potential
 * factors (i.e., constructs an array containing the CIDRs that contain $Addr).
 * Returns false if $Addr is *not* an IPv6 address, and otherwise, returns the
 * contructed array.
 *
 * @param string $Addr Refer to the description above.
 * @param bool $ValidateOnly If true, just checks if the IP is valid only.
 * @param int $FactorLimit Maximum number of CIDRs to return (default: 128).
 * @return bool|array Refer to the description above.
 */
$CIDRAM['ExpandIPv6'] = function ($Addr, $ValidateOnly = false, $FactorLimit = 128) {
    /**
     * The REGEX pattern used by this `preg_match` call was adapted from the
     * IPv6 REGEX pattern that can be found at
     * http://sroze.io/2008/10/09/regex-ipv4-et-ipv6/
     */
    if (!preg_match(
        '/^(([0-9a-f]{1,4}\:){7}[0-9a-f]{1,4})|(([0-9a-f]{1,4}\:){6}\:[0-9a-' .
        'f]{1,4})|(([0-9a-f]{1,4}\:){5}\:([0-9a-f]{1,4}\:)?[0-9a-f]{1,4})|((' .
        '[0-9a-f]{1,4}\:){4}\:([0-9a-f]{1,4}\:){0,2}[0-9a-f]{1,4})|(([0-9a-f' .
        ']{1,4}\:){3}\:([0-9a-f]{1,4}\:){0,3}[0-9a-f]{1,4})|(([0-9a-f]{1,4}' .
        '\:){2}\:([0-9a-f]{1,4}\:){0,4}[0-9a-f]{1,4})|(([0-9a-f]{1,4}\:){6}(' .
        '(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b).){3}(\b((25[0-5])|(' .
        '1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|(([0-9a-f]{1,4}\:){0,5}\:((\b((25' .
        '[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b).){3}(\b((25[0-5])|(1\d{2})' .
        '|(2[0-4]\d)|(\d{1,2}))\b))|(\:\:([0-9a-f]{1,4}\:){0,5}((\b((25[0-5]' .
        ')|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b).){3}(\b((25[0-5])|(1\d{2})|(2[0' .
        '-4]\d)|(\d{1,2}))\b))|([0-9a-f]{1,4}\:\:([0-9a-f]{1,4}\:){0,5}[0-9a' .
        '-f]{1,4})|(\:\:([0-9a-f]{1,4}\:){0,6}[0-9a-f]{1,4})|(([0-9a-f]{1,4}' .
        '\:){1,7}\:)$/i',
    $Addr)) {
        return false;
    }
    if ($ValidateOnly) {
        return true;
    }
    $NAddr = $Addr;
    if (preg_match('/^\:\:/i', $NAddr)) {
        $NAddr = '0' . $NAddr;
    }
    if (preg_match('/\:\:$/i', $NAddr)) {
        $NAddr .= '0';
    }
    if (substr_count($NAddr, '::')) {
        $c = 7 - substr_count($Addr, ':');
        $Arr = array(':0:', ':0:0:', ':0:0:0:', ':0:0:0:0:', ':0:0:0:0:0:', ':0:0:0:0:0:0:');
        if (!isset($Arr[$c])) {
            return false;
        }
        $NAddr = str_replace('::', $Arr[$c], $Addr);
        unset($Arr);
    }
    $NAddr = explode(':', $NAddr);
    if (count($NAddr) !== 8) {
        return false;
    }
    $NAddr[0] = hexdec($NAddr[0]);
    $NAddr[1] = hexdec($NAddr[1]);
    $NAddr[2] = hexdec($NAddr[2]);
    $NAddr[3] = hexdec($NAddr[3]);
    $NAddr[4] = hexdec($NAddr[4]);
    $NAddr[5] = hexdec($NAddr[5]);
    $NAddr[6] = hexdec($NAddr[6]);
    $NAddr[7] = hexdec($NAddr[7]);
    $CIDRs = array();
    $Base = array(0, 0, 0, 0, 0, 0, 0, 0);
    for ($Cycle = 0; $Cycle < 8; $Cycle++) {
        for ($Size = 32768, $Step = 0; $Step < 16; $Step++, $Size /= 2) {
            $CIDR = $Step + ($Cycle * 16);
            $Base[$Cycle] = dechex(floor($NAddr[$Cycle] / $Size) * $Size);
            $CIDRs[$CIDR] = $Base[0] . ':' . $Base[1] . ':' . $Base[2] . ':' . $Base[3] . ':' . $Base[4] . ':' . $Base[5] . ':' . $Base[6] . ':' . $Base[7] . '/' . ($CIDR + 1);
            if ($CIDR >= $FactorLimit) {
                break 2;
            }
        }
    }
    if ($FactorLimit > 128) {
        $FactorLimit = 128;
    }
    for ($CIDR = 0; $CIDR < $FactorLimit; $CIDR++) {
        if (strpos($CIDRs[$CIDR], '::') !== false) {
            $CIDRs[$CIDR] = preg_replace('/(\:0)*\:\:(0\:)*/i', '::', $CIDRs[$CIDR], 1);
            $CIDRs[$CIDR] = str_replace('::0/', '::/', $CIDRs[$CIDR]);
            continue;
        }
        if (strpos($CIDRs[$CIDR], ':0:0/') !== false) {
            $CIDRs[$CIDR] = preg_replace('/(\:0){2,}\//i', '::/', $CIDRs[$CIDR], 1);
            continue;
        }
        if (strpos($CIDRs[$CIDR], ':0:0:') !== false) {
            $CIDRs[$CIDR] = preg_replace('/(\:0)+\:(0\:)+/i', '::', $CIDRs[$CIDR], 1);
            $CIDRs[$CIDR] = str_replace('::0/', '::/', $CIDRs[$CIDR]);
            continue;
        }
    }
    return $CIDRs;
};

/**
 * Checks CIDRs (generally, potential factors expanded from IP addresses)
 * against the IPv4/IPv6 signature files, and if any matches are found,
 * increments `$CIDRAM['BlockInfo']['SignatureCount']`, and
 * appends to `$CIDRAM['BlockInfo']['ReasonMessage']`.
 *
 * @param array $Files Which IPv4/IPv6 signature files to check against.
 * @param array $Factors Which CIDRs/factors to check against.
 * @return bool Returns true.
 */
$CIDRAM['CheckFactors'] = function ($Files, $Factors) use (&$CIDRAM) {
    $Counts = array(
        'Files' => count($Files),
        'Factors' => count($Factors)
    );
    if (!isset($CIDRAM['FileCache'])) {
        $CIDRAM['FileCache'] = array();
    }
    for ($FileIndex = 0; $FileIndex < $Counts['Files']; $FileIndex++) {
        if (!$Files[$FileIndex]) {
            continue;
        }
        if ($Counts['Factors'] === 32) {
            $DefTag = $Files[$FileIndex] . '-IPv4';
        } elseif ($Counts['Factors'] === 128) {
            $DefTag = $Files[$FileIndex] . '-IPv6';
        } else {
            $DefTag = $Files[$FileIndex] . '-Unknown';
        }
        $FileExtension = strtolower(substr($Files[$FileIndex], -4));
        if (!isset($CIDRAM['FileCache'][$Files[$FileIndex]])) {
            $CIDRAM['FileCache'][$Files[$FileIndex]] = $CIDRAM['ReadFile']($CIDRAM['Vault'] . $Files[$FileIndex]);
        }
        if (!$Files[$FileIndex] = $CIDRAM['FileCache'][$Files[$FileIndex]]) {
            continue;
        }
        if (
            $FileExtension === '.csv' &&
            strpos($Files[$FileIndex], "\n") === false &&
            strpos($Files[$FileIndex], "\r") === false &&
            strpos($Files[$FileIndex], ",") !== false
        ) {
            $Files[$FileIndex] = ',' . $Files[$FileIndex] . ',';
            $SigFormat = 'CSV';
        } else {
            $SigFormat = 'DAT';
        }
        if ($Counts['Factors'] === 32) {
            if ($SigFormat === 'CSV') {
                $NoCIDR = ',' . substr($Factors[31], 0, -3) . ',';
                $LastCIDR = ',' . $Factors[31] . ',';
            } else {
                $NoCIDR = "\n" . substr($Factors[31], 0, -3) . ' ';
                $LastCIDR = "\n" . $Factors[31] . ' ';
            }
        } elseif ($Counts['Factors'] === 128) {
            if ($SigFormat === 'CSV') {
                $NoCIDR = ',' . substr($Factors[127], 0, -4) . ',';
                $LastCIDR = ',' . $Factors[127] . ',';
            } else {
                $NoCIDR = "\n" . substr($Factors[127], 0, -4) . ' ';
                $LastCIDR = "\n" . $Factors[127] . ' ';
            }
        }
        if (strpos($Files[$FileIndex], $NoCIDR) !== false) {
            $Files[$FileIndex] = str_replace($NoCIDR, $LastCIDR, $Files[$FileIndex]);
        }
        if ($SigFormat === 'CSV') {
            $LN = ' ("' . $DefTag . '", L0:F' . $FileIndex . ')';
            for ($FactorIndex = 0; $FactorIndex < $Counts['Factors']; $FactorIndex++) {
                if ($Infractions = substr_count($Files[$FileIndex], ',' . $Factors[$FactorIndex] . ',')) {
                    if (!$CIDRAM['CIDRAM_sapi']) {
                        $CIDRAM['BlockInfo']['ReasonMessage'] = $CIDRAM['lang']['ReasonMessage_Generic'];
                        if (!empty($CIDRAM['BlockInfo']['WhyReason'])) {
                            $CIDRAM['BlockInfo']['WhyReason'] .= ', ';
                        }
                        $CIDRAM['BlockInfo']['WhyReason'] .= $CIDRAM['lang']['Short_Generic'] . $LN;
                    }
                    if (!empty($CIDRAM['BlockInfo']['Signatures'])) {
                        $CIDRAM['BlockInfo']['Signatures'] .= ', ';
                    }
                    $CIDRAM['BlockInfo']['Signatures'] .= $Factors[$FactorIndex];
                    $CIDRAM['BlockInfo']['SignatureCount'] += $Infractions;
                }
            }
            continue;
        }
        if (strpos($Files[$FileIndex], "\r") !== false) {
            $Files[$FileIndex] =
                (strpos($Files[$FileIndex], "\r\n")) ?
                str_replace("\r", '', $Files[$FileIndex]) :
                str_replace("\r", "\n", $Files[$FileIndex]);
        }
        $Files[$FileIndex] = "\n" . $Files[$FileIndex] . "\n";
        for ($FactorIndex = 0; $FactorIndex < $Counts['Factors']; $FactorIndex++) {
            $PosB = -1;
            while (true) {
                $PosA = strpos($Files[$FileIndex], "\n" . $Factors[$FactorIndex] . ' ', ($PosB + 1));
                if ($PosA === false) {
                    break;
                }
                $PosA += strlen($Factors[$FactorIndex]) + 2;
                if (!$PosB = strpos($Files[$FileIndex], "\n", $PosA)) {
                    break;
                }
                if (
                    ($PosX = strpos($Files[$FileIndex], "\nExpires: ", $PosA)) &&
                    ($PosY = strpos($Files[$FileIndex], "\n", ($PosX + 1))) &&
                    !substr_count($Files[$FileIndex], "\n\n", $PosA, ($PosX - $PosA + 1)) &&
                    ($Expires = $CIDRAM['FetchExpires'](substr($Files[$FileIndex], ($PosX + 10), ($PosY - $PosX - 10)))) &&
                    $Expires < $CIDRAM['Now']
                ) {
                    continue;
                }
                $Tag = (
                    ($PosX = strpos($Files[$FileIndex], "\nTag: ", $PosA)) &&
                    ($PosY = strpos($Files[$FileIndex], "\n", ($PosX + 1))) &&
                    !substr_count($Files[$FileIndex], "\n\n", $PosA, ($PosX - $PosA + 1))
                ) ? substr($Files[$FileIndex], ($PosX + 6), ($PosY - $PosX - 6)) : $DefTag;
                if (isset($CIDRAM['Ignore'][$Tag]) && $CIDRAM['Ignore'][$Tag]) {
                    continue;
                }
                if (
                    ($PosX = strpos($Files[$FileIndex], "\n---\n", $PosA)) &&
                    ($PosY = strpos($Files[$FileIndex], "\n\n", ($PosX + 1))) &&
                    !substr_count($Files[$FileIndex], "\n\n", $PosA, ($PosX - $PosA + 1))
                ) {
                    $YAML = $CIDRAM['YAML'](substr($Files[$FileIndex], ($PosX + 5), ($PosY - $PosX - 5)), $CIDRAM['Config']);
                }
                $LN = ' ("' . $Tag . '", L' . substr_count($Files[$FileIndex], "\n", 0, $PosA) . ':F' . $FileIndex . ')';
                $Signature = substr($Files[$FileIndex], $PosA, ($PosB - $PosA));
                if (!$Category = substr($Signature, 0, strpos($Signature, ' '))) {
                    $Category = $Signature;
                } else {
                    $Signature = substr($Signature, strpos($Signature, ' ') + 1);
                }
                if ($Category === 'Run' && !$CIDRAM['CIDRAM_sapi']) {
                    if (file_exists($CIDRAM['Vault'] . $Signature)) {
                        require_once $CIDRAM['Vault'] . $Signature;
                    } else {
                        throw new \Exception($CIDRAM['ParseVars'](
                            array('FileName' => $Signature),
                            '[CIDRAM] ' . $CIDRAM['lang']['Error_MissingRequire']
                        ));
                    }
                } elseif ($Category === 'Whitelist') {
                    $CIDRAM['BlockInfo']['Signatures'] = $CIDRAM['BlockInfo']['ReasonMessage'] = $CIDRAM['BlockInfo']['WhyReason'] = '';
                    $CIDRAM['BlockInfo']['SignatureCount'] = 0;
                    $CIDRAM['Whitelisted'] = true;
                    break 3;
                } elseif ($Category === 'Greylist') {
                    $CIDRAM['BlockInfo']['Signatures'] = $CIDRAM['BlockInfo']['ReasonMessage'] = $CIDRAM['BlockInfo']['WhyReason'] = '';
                    $CIDRAM['BlockInfo']['SignatureCount'] = 0;
                    break 2;
                } elseif ($Category === 'Deny') {
                    if ($Signature === 'Bogon' && !$CIDRAM['CIDRAM_sapi']) {
                        if (!$CIDRAM['Config']['signatures']['block_bogons']) {
                            continue;
                        }
                        $CIDRAM['BlockInfo']['ReasonMessage'] = $CIDRAM['lang']['ReasonMessage_Bogon'];
                        if (!empty($CIDRAM['BlockInfo']['WhyReason'])) {
                            $CIDRAM['BlockInfo']['WhyReason'] .= ', ';
                        }
                        $CIDRAM['BlockInfo']['WhyReason'] .= $CIDRAM['lang']['Short_Bogon'] . $LN;
                    } elseif ($Signature === 'Cloud' && !$CIDRAM['CIDRAM_sapi']) {
                        if (!$CIDRAM['Config']['signatures']['block_cloud']) {
                            continue;
                        }
                        $CIDRAM['BlockInfo']['ReasonMessage'] = $CIDRAM['lang']['ReasonMessage_Cloud'];
                        if (!empty($CIDRAM['BlockInfo']['WhyReason'])) {
                            $CIDRAM['BlockInfo']['WhyReason'] .= ', ';
                        }
                        $CIDRAM['BlockInfo']['WhyReason'] .= $CIDRAM['lang']['Short_Cloud'] . $LN;
                    } elseif ($Signature === 'Generic' && !$CIDRAM['CIDRAM_sapi']) {
                        if (!$CIDRAM['Config']['signatures']['block_generic']) {
                            continue;
                        }
                        $CIDRAM['BlockInfo']['ReasonMessage'] = $CIDRAM['lang']['ReasonMessage_Generic'];
                        if (!empty($CIDRAM['BlockInfo']['WhyReason'])) {
                            $CIDRAM['BlockInfo']['WhyReason'] .= ', ';
                        }
                        $CIDRAM['BlockInfo']['WhyReason'] .= $CIDRAM['lang']['Short_Generic'] . $LN;
                    } elseif ($Signature === 'Proxy' && !$CIDRAM['CIDRAM_sapi']) {
                        if (!$CIDRAM['Config']['signatures']['block_proxies']) {
                            continue;
                        }
                        $CIDRAM['BlockInfo']['ReasonMessage'] = $CIDRAM['lang']['ReasonMessage_Proxy'];
                        if (!empty($CIDRAM['BlockInfo']['WhyReason'])) {
                            $CIDRAM['BlockInfo']['WhyReason'] .= ', ';
                        }
                        $CIDRAM['BlockInfo']['WhyReason'] .= $CIDRAM['lang']['Short_Proxy'] . $LN;
                    } elseif ($Signature === 'Spam' && !$CIDRAM['CIDRAM_sapi']) {
                        if (!$CIDRAM['Config']['signatures']['block_spam']) {
                            continue;
                        }
                        $CIDRAM['BlockInfo']['ReasonMessage'] = $CIDRAM['lang']['ReasonMessage_Spam'];
                        if (!empty($CIDRAM['BlockInfo']['WhyReason'])) {
                            $CIDRAM['BlockInfo']['WhyReason'] .= ', ';
                        }
                        $CIDRAM['BlockInfo']['WhyReason'] .= $CIDRAM['lang']['Short_Spam'] . $LN;
                    } else {
                        $CIDRAM['BlockInfo']['ReasonMessage'] = $Signature;
                        if (!empty($CIDRAM['BlockInfo']['WhyReason'])) {
                            $CIDRAM['BlockInfo']['WhyReason'] .= ', ';
                        }
                        $CIDRAM['BlockInfo']['WhyReason'] .= $Signature . $LN;
                    }
                    if (!empty($CIDRAM['BlockInfo']['Signatures'])) {
                        $CIDRAM['BlockInfo']['Signatures'] .= ', ';
                    }
                    $CIDRAM['BlockInfo']['Signatures'] .= $Factors[$FactorIndex];
                    $CIDRAM['BlockInfo']['SignatureCount']++;
                }
            }
        }
    }
    return true;
};

/**
 * Initialises all IPv4/IPv6 tests.
 *
 * @param string $Addr The IP address to check.
 * @return bool Returns false if all tests fail, and otherwise, returns true.
 */
$CIDRAM['RunTests'] = function ($Addr) use (&$CIDRAM) {
    if (!isset($CIDRAM['BlockInfo'])) {
        return false;
    }
    $CIDRAM['Ignore'] = $CIDRAM['FetchIgnores']();
    $CIDRAM['Whitelisted'] = false;
    $CIDRAM['LastTestIP'] = 0;
    if ($IPv4Factors = $CIDRAM['ExpandIPv4']($Addr)) {
        $IPv4Files = empty(
            $CIDRAM['Config']['signatures']['ipv4']
        ) ? array() : explode(',', $CIDRAM['Config']['signatures']['ipv4']);
        try {
            $IPv4Test = $CIDRAM['CheckFactors']($IPv4Files, $IPv4Factors);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
        if ($IPv4Test) {
            $CIDRAM['LastTestIP'] = 4;
        }
    } else {
        $IPv4Test = false;
    }
    if ($IPv6Factors = $CIDRAM['ExpandIPv6']($Addr)) {
        $IPv6Files = empty(
            $CIDRAM['Config']['signatures']['ipv6']
        ) ? array() : explode(',', $CIDRAM['Config']['signatures']['ipv6']);
        try {
            $IPv6Test = $CIDRAM['CheckFactors']($IPv6Files, $IPv6Factors);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
        if ($IPv6Test) {
            $CIDRAM['LastTestIP'] = 6;
        }
    } else {
        $IPv6Test = false;
    }
    return ($IPv4Test || $IPv6Test);
};

/**
 * A very simple closure for preparing validator/fixer messages in CLI-mode.
 *
 * @param string $lvl Error level.
 * @param string $msg The unprepared message (in).
 * @return string The prepared message (out).
 */
$CIDRAM['ValidatorMsg'] = function ($lvl, $msg) {
    return wordwrap(sprintf(' [%s] %s', $lvl, $msg), 78, "\n ") . "\n\n";
};

/**
 * Reduces code duplicity (the contained code used by multiple parts of the
 * script for dealing with expiry tags).
 *
 * @param string $in Expiry tag.
 * @return int|bool A unix timestamp representing the expiry tag, or false if
 *      the expiry tag doesn't contain a valid ISO 8601 date/time.
 */
$CIDRAM['FetchExpires'] = function ($in) {
    if (
        preg_match(
            '/^([12][0-9]{3})(?:\xe2\x88\x92|[\x2d-\x2f\x5c])?(0[1-9]|1[0-2])(?:\xe2' .
            '\x88\x92|[\x2d-\x2f\x5c])?(0[1-9]|[1-2][0-9]|3[01])\x20?T?([01][0-9]|2[' .
            '0-3])[\x2d\x2e\x3a]?([0-5][0-9])[\x2d\x2e\x3a]?([0-5][0-9])$/i',
        $in, $Arr) ||
        preg_match(
            '/^([12][0-9]{3})(?:\xe2\x88\x92|[\x2d-\x2f\x5c])?(0[1-9]|1[0-2])(?:\xe2' .
            '\x88\x92|[\x2d-\x2f\x5c])?(0[1-9]|[1-2][0-9]|3[01])\x20?T?([01][0-9]|2[' .
            '0-3])[\x2d\x2e\x3a]?([0-5][0-9])$/i',
        $in, $Arr) ||
        preg_match(
            '/^([12][0-9]{3})(?:\xe2\x88\x92|[\x2d-\x2f\x5c])?(0[1-9]|1[0-2])(?:\xe2' .
            '\x88\x92|[\x2d-\x2f\x5c])?(0[1-9]|[1-2][0-9]|3[01])\x20?T?([01][0-9]|2[' .
            '0-3])$/i',
        $in, $Arr) ||
        preg_match(
            '/^([12][0-9]{3})(?:\xe2\x88\x92|[\x2d-\x2f\x5c])?(0[1-9]|1[0-2])(?:\xe2' .
            '\x88\x92|[\x2d-\x2f\x5c])?(0[1-9]|[1-2][0-9]|3[01])$/i',
        $in, $Arr) ||
        preg_match('/^([12][0-9]{3})(?:\xe2\x88\x92|[\x2d-\x2f\x5c])?(0[1-9]|1[0-2])$/i', $in, $Arr) ||
        preg_match('/^([12][0-9]{3})$/i', $in, $Arr)
    ) {
        $Arr = array(
            (int)$Arr[1],
            isset($Arr[2]) ? (int)$Arr[2] : 1,
            isset($Arr[3]) ? (int)$Arr[3] : 1,
            isset($Arr[4]) ? (int)$Arr[4] : 0,
            isset($Arr[5]) ? (int)$Arr[5] : 0,
            isset($Arr[6]) ? (int)$Arr[6] : 0
        );
        $Expires = mktime($Arr[3], $Arr[4], $Arr[5], $Arr[1], $Arr[2], $Arr[0]);
        return ($Expires) ? $Expires : false;
    }
    return false;
};

/**
 * A simple closure for replacing date/time placeholders with corresponding
 * date/time information. Used by the logfiles and some timestamps.
 *
 * @param int $Time A unix timestamp.
 * @param string|array $In An input or an array of inputs to manipulate.
 * @return string|array The adjusted input(/s).
 */
$CIDRAM['TimeFormat'] = function ($Time, $In) use (&$CIDRAM) {
    $Time = date('dmYHisDMP', $Time);
    $values = array(
        'dd' => substr($Time, 0, 2),
        'mm' => substr($Time, 2, 2),
        'yyyy' => substr($Time, 4, 4),
        'yy' => substr($Time, 6, 2),
        'hh' => substr($Time, 8, 2),
        'ii' => substr($Time, 10, 2),
        'ss' => substr($Time, 12, 2),
        'Day' => substr($Time, 14, 3),
        'Mon' => substr($Time, 17, 3),
        'tz' => substr($Time, 20, 3) . substr($Time, 24, 2),
        't:z' => substr($Time, 20, 6)
    );
    $values['d'] = (int)$values['dd'];
    $values['m'] = (int)$values['mm'];
    if (is_array($In)) {
        return array_map(function ($Item) use (&$values, &$CIDRAM) {
            return $CIDRAM['ParseVars']($values, $Item);
        }, $In);
    }
    return $CIDRAM['ParseVars']($values, $In);
};

/**
 * Normalises values defined by the YAML closure.
 *
 * @param string|int|bool $Value The value to be normalised.
 * @param int $ValueLen The length of the value to be normalised.
 * @param string|int|bool $ValueLow The value to be normalised, lowercased.
 */
$CIDRAM['YAML-Normalise-Value'] = function (&$Value, $ValueLen, $ValueLow) {
    if (substr($Value, 0, 1) === '"' && substr($Value, $ValueLen - 1) === '"') {
        $Value = substr($Value, 1, $ValueLen - 2);
    } elseif (substr($Value, 0, 1) === '\'' && substr($Value, $ValueLen - 1) === '\'') {
        $Value = substr($Value, 1, $ValueLen - 2);
    } elseif ($ValueLow === 'true' || $ValueLow === 'y') {
        $Value = true;
    } elseif ($ValueLow === 'false' || $ValueLow === 'n') {
        $Value = false;
    } elseif (substr($Value, 0, 2) === '0x' && ($HexTest = substr($Value, 2)) && !preg_match('/[^a-f0-9]/i', $HexTest) && !($ValueLen % 2)) {
        $Value = hex2bin($HexTest);
    } else {
        $ValueInt = (int)$Value;
        if (strlen($ValueInt) === $ValueLen && $Value == $ValueInt && $ValueLen > 1) {
            $Value = $ValueInt;
        }
    }
    if (!$Value) {
        $Value = false;
    }
};

/**
 * A simplified YAML-like parser. Note: This is intended to adequately serve
 * the needs of this package in a way that should feel familiar to users of
 * YAML, but it isn't a true YAML implementation and it doesn't adhere to any
 * specifications, official or otherwise.
 *
 * @param string $In The data to parse.
 * @param array $Arr Where to save the results.
 * @param bool $VM Validator Mode (if true, results won't be saved).
 * @param int $Depth Tab depth (inherited through recursion; ignore it).
 * @return bool Returns false if errors are encountered, and true otherwise.
 */
$CIDRAM['YAML'] = function ($In, &$Arr, $VM = false, $Depth = 0) use (&$CIDRAM) {
    if (!is_array($Arr)) {
        if ($VM) {
            return false;
        }
        $Arr = array();
    }
    if (!substr_count($In, "\n")) {
        return false;
    }
    $In = str_replace("\r", '', $In);
    $Key = $Value = $SendTo = '';
    $TabLen = $SoL = 0;
    while ($SoL !== false) {
        if (($EoL = strpos($In, "\n", $SoL)) === false) {
            $ThisLine = substr($In, $SoL);
        } else {
            $ThisLine = substr($In, $SoL, $EoL - $SoL);
        }
        $SoL = ($EoL === false) ? false : $EoL + 1;
        $ThisLine = preg_replace(array("/#.*$/", "/\x20+$/"), '', $ThisLine);
        if (empty($ThisLine) || $ThisLine === "\n") {
            continue;
        }
        $ThisTab = 0;
        while (($Chr = substr($ThisLine, $ThisTab, 1)) && ($Chr === ' ' || $Chr === "\t")) {
            $ThisTab++;
        }
        if ($ThisTab > $Depth) {
            if ($TabLen === 0) {
                $TabLen = $ThisTab;
            }
            $SendTo .= $ThisLine . "\n";
            continue;
        } elseif ($ThisTab < $Depth) {
            return false;
        } elseif (!empty($SendTo)) {
            if (empty($Key)) {
                return false;
            }
            if (!isset($Arr[$Key])) {
                if ($VM) {
                    return false;
                }
                $Arr[$Key] = false;
            }
            if (!$CIDRAM['YAML']($SendTo, $Arr[$Key], $VM, $TabLen)) {
                return false;
            }
            $SendTo = '';
        }
        if (substr($ThisLine, -1) === ':') {
            $Key = substr($ThisLine, $ThisTab, -1);
            $KeyLen = strlen($Key);
            $KeyLow = strtolower($Key);
            $CIDRAM['YAML-Normalise-Value']($Key, $KeyLen, $KeyLow);
            if (!isset($Arr[$Key])) {
                if ($VM) {
                    return false;
                }
                $Arr[$Key] = false;
            }
        } elseif (substr($ThisLine, $ThisTab, 2) === '- ') {
            $Value = substr($ThisLine, $ThisTab + 2);
            $ValueLen = strlen($Value);
            $ValueLow = strtolower($Value);
            $CIDRAM['YAML-Normalise-Value']($Value, $ValueLen, $ValueLow);
            if (!$VM && $ValueLen > 0) {
                $Arr[] = $Value;
            }
        } elseif (($DelPos = strpos($ThisLine, ': ')) !== false) {
            $Key = substr($ThisLine, $ThisTab, $DelPos - $ThisTab);
            $KeyLen = strlen($Key);
            $KeyLow = strtolower($Key);
            $CIDRAM['YAML-Normalise-Value']($Key, $KeyLen, $KeyLow);
            if (!$Key) {
                return false;
            }
            $Value = substr($ThisLine, $ThisTab + $KeyLen + 2);
            $ValueLen = strlen($Value);
            $ValueLow = strtolower($Value);
            $CIDRAM['YAML-Normalise-Value']($Value, $ValueLen, $ValueLow);
            if (!$VM && $ValueLen > 0) {
                $Arr[$Key] = $Value;
            }
        } elseif (strpos($ThisLine, ':') === false && strlen($ThisLine) > 1) {
            $Key = $ThisLine;
            $KeyLen = strlen($Key);
            $KeyLow = strtolower($Key);
            $CIDRAM['YAML-Normalise-Value']($Key, $KeyLen, $KeyLow);
            if (!isset($Arr[$Key])) {
                if ($VM) {
                    return false;
                }
                $Arr[$Key] = false;
            }
        }
    }
    if (!empty($SendTo) && !empty($Key)) {
        if (!isset($Arr[$Key])) {
            if ($VM) {
                return false;
            }
            $Arr[$Key] = array();
        }
        if (!$CIDRAM['YAML']($SendTo, $Arr[$Key], $VM, $TabLen)) {
            return false;
        }
    }
    return true;
};

/**
 * Validates or ensures that two different sets of component metadata share the
 * same base elements (or components). One set acts as a model for which base
 * elements are expected, and if additional/superfluous entries are found in
 * the other set (the base), they'll be removed. Installed components are
 * ignored as to future-proof legacy support (just removes non-installed
 * components).
 *
 * @param string $Base The base set (generally, the local copy).
 * @param string $Model The model set (generally, the remote copy).
 * @param bool $Validate Validate (true) or ensure congruency (false; default).
 * @return string|bool If $Validate is true, returns true|false according to
 *      whether the sets are congruent. If $Validate is false, returns the
 *      corrected $Base set.
 */
$CIDRAM['Congruency'] = function ($Base, $Model, $Validate = false) use (&$CIDRAM) {
    if (empty($Base) || empty($Model)) {
        return $Validate ? false : '';
    }
    $BaseArr = $ModelArr = array();
    $CIDRAM['YAML']($Base, $BaseArr);
    $CIDRAM['YAML']($Model, $ModelArr);
    foreach ($BaseArr as $Element => $Data) {
        if (!isset($Data['Version']) && !isset($Data['Files']) && !isset($ModelArr[$Element])) {
            if ($Validate) {
                return false;
            }
            $Base = preg_replace("~\n" . $Element . ":?(\n [^\n]*)*\n~i", "\n", $Base);
        }
    }
    return $Validate ? true : $Base;
};

/**
 * Fix incorrect typecasting for some for some variables that sometimes default
 * to strings instead of booleans or integers.
 */
$CIDRAM['AutoType'] = function (&$Var, $Type = '') use (&$CIDRAM) {
    if ($Type === 'string' || $Type === 'timezone') {
        $Var = (string)$Var;
    } elseif ($Type === 'int' || $Type === 'integer') {
        $Var = (int)$Var;
    } elseif ($Type === 'real' || $Type === 'double' || $Type === 'float') {
        $Var = (real)$Var;
    } elseif ($Type === 'bool' || $Type === 'boolean') {
        $Var = (strtolower($Var) !== 'false' && $Var);
    } elseif ($Type === 'kb') {
        $Var = $CIDRAM['ReadBytes']($Var, 1);
    } else {
        $LVar = strtolower($Var);
        if ($LVar === 'true') {
            $Var = true;
        } elseif ($LVar === 'false') {
            $Var = false;
        } elseif ($Var !== true && $Var !== false) {
            $Var = (int)$Var;
        }
    }
};

/**
 * Used to send cURL requests.
 *
 * @param string $URI The resource to request.
 * @param array $Params (Optional) An associative array of key-value pairs to
 *      to send along with the request.
 * @param string $Timeout An optional timeout limit (defaults to 12 seconds).
 * @return string The results of the request.
 */
$CIDRAM['Request'] = function ($URI, $Params = '', $Timeout = '') use (&$CIDRAM) {
    if (!$Timeout) {
        $Timeout = $CIDRAM['Timeout'];
    }

    /** Initialise the cURL session. */
    $Request = curl_init($URI);

    $LCURI = strtolower($URI);
    $SSL = (substr($LCURI, 0, 6) === 'https:');

    curl_setopt($Request, CURLOPT_FRESH_CONNECT, true);
    curl_setopt($Request, CURLOPT_HEADER, false);
    if (empty($Params)) {
        curl_setopt($Request, CURLOPT_POST, false);
    } else {
        curl_setopt($Request, CURLOPT_POST, true);
        curl_setopt($Request, CURLOPT_POSTFIELDS, $Params);
    }
    if ($SSL) {
        curl_setopt($Request, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
        curl_setopt($Request, CURLOPT_SSL_VERIFYPEER, false);
    }
    curl_setopt($Request, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($Request, CURLOPT_MAXREDIRS, 1);
    curl_setopt($Request, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($Request, CURLOPT_TIMEOUT, $Timeout);
    curl_setopt($Request, CURLOPT_USERAGENT, $CIDRAM['ScriptUA']);

    /** Execute and get the response. */
    $Response = curl_exec($Request);

    /** Close the cURL session. */
    curl_close($Request);

    /** Return the results of the request. */
    return $Response;
};

/**
 * Can be used to delete some files via the front-end.
 *
 * @param string $File The file to delete.
 * @return bool Success or failure.
 */
$CIDRAM['Delete'] = function ($File) use (&$CIDRAM) {
    if (!empty($File) && file_exists($CIDRAM['Vault'] . $File) && $CIDRAM['Traverse']($File)) {
        if (!unlink($CIDRAM['Vault'] . $File)) {
            return false;
        }
        $CIDRAM['DeleteDirectory']($File);
        return true;
    }
    return false;
};

/**
 * Can be used to delete some empty directories via the front-end.
 *
 * @param string $Dir The directory to delete.
 */
$CIDRAM['DeleteDirectory'] = function ($Dir) use (&$CIDRAM) {
    while (strrpos($Dir, '/') !== false || strrpos($Dir, "\\") !== false) {
        $Separator = (strrpos($Dir, '/') !== false) ? '/' : "\\";
        $Dir = substr($Dir, 0, strrpos($Dir, $Separator));
        if (is_dir($CIDRAM['Vault'] . $Dir) && $CIDRAM['FileManager-IsDirEmpty']($CIDRAM['Vault'] . $Dir)) {
            rmdir($CIDRAM['Vault'] . $Dir);
        } else {
            break;
        }
    }
};

/**
 * Performs reverse DNS lookups for IPv4 IP addresses, to resolve their
 * hostnames. This is functionally equivalent to the in-built PHP function
 * "gethostbyaddr", but with the added benefits of being able to specify which
 * DNS servers to use for lookups, and of being able to enforce timeout limits,
 * which should help to avoid some of the problems normally associated with
 * using "gethostbyaddr".
 *
 * @param string $Addr The IPv4 IP address to look up.
 * @param string $DNS An optional, comma delimited list of DNS servers to use.
 * @param string $Timeout The timeout limit (optional; defaults to 5 seconds).
 * @return string The hostname on success, or the IP address on failure.
 */
$CIDRAM['DNS-Reverse-IPv4'] = function ($Addr, $DNS = '', $Timeout = 5) use (&$CIDRAM) {
    if (!isset($CIDRAM['_allow_url_fopen'])) {
        $CIDRAM['_allow_url_fopen'] = ini_get('allow_url_fopen');
        $CIDRAM['_allow_url_fopen'] = !(!$CIDRAM['_allow_url_fopen'] || $CIDRAM['_allow_url_fopen'] == 'Off');
    }
    if (!function_exists('fsockopen') || !$CIDRAM['_allow_url_fopen']) {
        return $Addr;
    }
    if (isset($CIDRAM['Cache']['DNS-Reverses'][$Addr]['Host'])) {
        return $CIDRAM['Cache']['DNS-Reverses'][$Addr]['Host'];
    }
    if (!isset($CIDRAM['Cache']['DNS-Reverses'])) {
        $CIDRAM['Cache']['DNS-Reverses'] = array();
    }
    $CIDRAM['Cache']['DNS-Reverses'][$Addr] = array('Host' => '', 'Time' => $CIDRAM['Now'] + 21600);
    $CIDRAM['CacheModified'] = true;

    if (!$DNS && !$DNS = $CIDRAM['Config']['general']['default_dns']) {
        return $Addr;
    }
    $DNS = explode(',', $DNS);

    $LeftPad = str_pad(rand(0, 99), 2, '0', STR_PAD_LEFT) . "\1\0\0\1\0\0\0\0\0\0";
    if (preg_match(
        '/^([01]?[0-9]{1,2}|2[0-4][0-9]|25[0-5])\.([01]?[0-9]{1,2}|2[0-4][0-' .
        '9]|25[0-5])\.([01]?[0-9]{1,2}|2[0-4][0-9]|25[0-5])\.([01]?[0-9]{1,2' .
        '}|2[0-4][0-9]|25[0-5])$/i',
    $Addr, $Octets)) {
        $Lookup =
            chr(strlen($Octets[4])) . $Octets[4] .
            chr(strlen($Octets[3])) . $Octets[3] .
            chr(strlen($Octets[2])) . $Octets[2] .
            chr(strlen($Octets[1])) . $Octets[1] .
            "\7in-addr\4arpa\0\0\x0c\0\1";
    } else {
        return '';
    }
    foreach ($DNS as $Server) {
        if (!empty($Response) || !$Server) {
            break;
        }
        $Handle = fsockopen('udp://' . $Server, 53);
        fwrite($Handle, $LeftPad . $Lookup);
        stream_set_timeout($Handle, $Timeout);
        $Response = fread($Handle, 1024);
        fclose($Handle);
    }
    if (empty($Response)) {
        return $CIDRAM['Cache']['DNS-Reverses'][$Addr]['Host'] = $Addr;
    }
    $Host = '';
    if (($Pos = strpos($Response, $Lookup)) !== false) {
        $Pos += strlen($Lookup) + 12;
        while (($Byte = substr($Response, $Pos, 1)) && $Byte !== "\0") {
            if ($Host) {
                $Host .= '.';
            }
            $Len = hexdec(bin2hex($Byte));
            $Host .= substr($Response, $Pos + 1, $Len);
            $Pos += 1 + $Len;
        }
    }
    return $CIDRAM['Cache']['DNS-Reverses'][$Addr]['Host'] = preg_replace('/[^0-9a-z._~-]/i', '', $Host) ?: $Addr;
};

/**
 * Performs forward DNS lookups for hostnames, to resolve their IP address.
 * This is functionally equivalent to the in-built PHP function
 * "gethostbyname", but with the added benefits of having IPv6 support and of
 * being able to enforce timeout limits, which should help to avoid some of the
 * problems normally associated with using "gethostbyname").
 *
 * @param string $Host The hostname to look up.
 * @param string $Timeout The timeout limit (optional; defaults to 5 seconds).
 * @return string The IP address on success, or an empty string on failure.
 */
$CIDRAM['DNS-Resolve'] = function ($Host, $Timeout = 5) use (&$CIDRAM) {
    if (isset($CIDRAM['Cache']['DNS-Forwards'][$Host]['IPAddr'])) {
        return $CIDRAM['Cache']['DNS-Forwards'][$Host]['IPAddr'];
    }
    if (!isset($CIDRAM['Cache']['DNS-Forwards'])) {
        $CIDRAM['Cache']['DNS-Forwards'] = array();
    }
    $CIDRAM['Cache']['DNS-Forwards'][$Host] = array('IPAddr' => '', 'Time' => $CIDRAM['Now'] + 21600);
    $CIDRAM['CacheModified'] = true;

    static $Valid = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz-._~';
    $Host = urlencode($Host);
    if (($HostLen = strlen($Host)) > 253) {
        return '';
    }
    $URI = 'https://dns.google.com/resolve?name=' . urlencode($Host) . '&random_padding=';
    $PadLen = 204 - $HostLen;
    if ($PadLen < 1) {
        $PadLen = 972 - $HostLen;
    }
    while ($PadLen > 0) {
        $PadLen--;
        $URI .= str_shuffle($Valid)[0];
    }

    if (!$Results = json_decode($CIDRAM['Request']($URI, '', $Timeout), true)) {
        return '';
    }
    return $CIDRAM['Cache']['DNS-Forwards'][$Host]['IPAddr'] =
        empty($Results['Answer'][0]['data']) ? '' : preg_replace('/[^0-9a-f.:]/i', '', $Results['Answer'][0]['data']);
};

/**
 * Distinguishes between bots masquerading as popular search engines and real,
 * legitimate search engines. Tracking is disabled for real, legitimate search
 * engines, and those masquerading as them are blocked. If DNS is unresolvable
 * and/or if it can't be determined whether a request has originated from a
 * fake or a legitimate source, it takes no action (i.e., doesn't mess with
 * tracking and doesn't block anything).
 *
 * @param string|array $Domains Accepted domain/hostname partials.
 * @param string $Friendly A friendly name to use in logfiles.
 * @param bool $ReverseOnly Skips forward lookups if true.
 * @return bool Returns true when a determination is successfully made, and
 *      false when a determination isn't able to be made.
 */
$CIDRAM['DNS-Reverse-Forward'] = function ($Domains, $Friendly, $ReverseOnly = false) use (&$CIDRAM) {
    if (empty($CIDRAM['Hostname'])) {
        /** Fetch the hostname. */
        $CIDRAM['Hostname'] = $CIDRAM['DNS-Reverse-IPv4']($CIDRAM['BlockInfo']['IPAddr']);
    }
    /** Force domains to be an array. */
    $CIDRAM['Arrayify']($Domains);
    /** Do nothing more if we weren't able to resolve the DNS hostname. */
    if (!$CIDRAM['Hostname'] || $CIDRAM['Hostname'] === $CIDRAM['BlockInfo']['IPAddr']) {
        return false;
    }
    $Pass = false;
    /** Compare the hostname against the accepted domain/hostname partials. */
    foreach ($Domains as $Domain) {
        $Len = strlen($Domain) * -1;
        if (substr($CIDRAM['Hostname'], $Len) === $Domain) {
            $Pass = true;
            break;
        }
    }
    /**
     * Resolve the hostname to the original IP address (if $ReverseOnly is
     * false); Act according to the results and return.
     */
    if ($Pass && (
        $ReverseOnly || $CIDRAM['DNS-Resolve']($CIDRAM['Hostname']) === $CIDRAM['BlockInfo']['IPAddr'])
    ) {
        /** It's the real deal; Disable tracking. */
        $CIDRAM['Trackable'] = false;
    } else {
        /** It's a fake; Block it. */
        $Reason = $CIDRAM['ParseVars'](array('ua' => $Friendly), $CIDRAM['lang']['fake_ua']);
        $CIDRAM['BlockInfo']['ReasonMessage'] = $Reason;
        if (!empty($CIDRAM['BlockInfo']['WhyReason'])) {
            $CIDRAM['BlockInfo']['WhyReason'] .= ', ';
        }
        $CIDRAM['BlockInfo']['WhyReason'] .= $Reason;
        if (!empty($CIDRAM['BlockInfo']['Signatures'])) {
            $CIDRAM['BlockInfo']['Signatures'] .= ', ';
        }
        $Debug = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        $CIDRAM['BlockInfo']['Signatures'] .= basename($Debug['file']) . ':L' . $Debug['line'];
        $CIDRAM['BlockInfo']['SignatureCount']++;
    }
    return true;
};

/**
 * Checks whether an IP is expected. If so, tracking is disabled for the IP
 * being checked, and if not, the request is blocked. Has no return value.
 *
 * @param string|array $Expected IPs expected.
 * @param string $Friendly A friendly name to use in logfiles.
 */
$CIDRAM['UA-IP-Match'] = function ($Expected, $Friendly) use (&$CIDRAM) {
    $CIDRAM['Arrayify']($Expected);
    /** Compare the actual IP of the request against the expected IPs. */
    if (in_array($CIDRAM['BlockInfo']['IPAddr'], $Expected)) {
        /** Disable tracking. */
        $CIDRAM['Trackable'] = false;
    } else {
        /** Block it. */
        $Reason = $CIDRAM['ParseVars'](array('ua' => $Friendly), $CIDRAM['lang']['fake_ua']);
        $CIDRAM['BlockInfo']['ReasonMessage'] = $Reason;
        if (!empty($CIDRAM['BlockInfo']['WhyReason'])) {
            $CIDRAM['BlockInfo']['WhyReason'] .= ', ';
        }
        $CIDRAM['BlockInfo']['WhyReason'] .= $Reason;
        if (!empty($CIDRAM['BlockInfo']['Signatures'])) {
            $CIDRAM['BlockInfo']['Signatures'] .= ', ';
        }
        $Debug = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        $CIDRAM['BlockInfo']['Signatures'] .= basename($Debug['file']) . ':L' . $Debug['line'];
        $CIDRAM['BlockInfo']['SignatureCount']++;
    }
};

/**
 * A default closure for handling signature triggers within module files.
 *
 * @param bool $Condition Include any variable or PHP code which can be
 *      evaluated for truthiness. Truthiness is evaluated, and if true, the
 *      signature is "triggered". If false, the signature is *not* "triggered".
 * @param string $ReasonShort Cited in the "Why Blocked" field when the
 *      signature is triggered and thus included within logfile entries.
 * @param string $ReasonLong Message displayed to the user/client when blocked,
 *      to explain why they've been blocked. Optional. Defaults to the standard
 *      "Access Denied!" message.
 * @param array $DefineOptions An optional array containing key/value pairs,
 *      used to define configuration options specific to the request instance.
 *      Configuration options will be applied when the signature is triggered.
 * @return bool Returns true if the signature was triggered, and false if it
 *      wasn't. Should correspond to the truthiness of $Condition.
 */
$CIDRAM['Trigger'] = function ($Condition, $ReasonShort, $ReasonLong = '', $DefineOptions = array()) use (&$CIDRAM) {
    if (!$Condition) {
        return false;
    }
    if (!$ReasonLong) {
        $ReasonLong = $CIDRAM['lang']['denied'];
    }
    if (is_array($DefineOptions) && !empty($DefineOptions)) {
        foreach ($DefineOptions as $CatKey => $CatValue) {
            if (is_array($CatValue) && !empty($CatValue)) {
                foreach ($CatValue as $OptionKey => $OptionValue) {
                    $CIDRAM['Config'][$CatKey][$OptionKey] = $OptionValue;
                }
            }
        }
    }
    $CIDRAM['BlockInfo']['ReasonMessage'] = $ReasonLong;
    if (!empty($CIDRAM['BlockInfo']['WhyReason'])) {
        $CIDRAM['BlockInfo']['WhyReason'] .= ', ';
    }
    $CIDRAM['BlockInfo']['WhyReason'] .= $ReasonShort;
    if (!empty($CIDRAM['BlockInfo']['Signatures'])) {
        $CIDRAM['BlockInfo']['Signatures'] .= ', ';
    }
    $Debug = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
    $CIDRAM['BlockInfo']['Signatures'] .= basename($Debug['file']) . ':L' . $Debug['line'];
    $CIDRAM['BlockInfo']['SignatureCount']++;
    return true;
};

/**
 * A default closure for handling signature bypasses within module files.
 *
 * @param bool $Condition Include any variable or PHP code which can be
 *      evaluated for truthiness. Truthiness is evaluated, and if true, the
 *      bypass is "triggered". If false, the bypass is *not* "triggered".
 * @param string $ReasonShort Cited in the "Why Blocked" field when the
 *      bypass is triggered (included within logfile entries if there are still
 *      other preexisting signatures which have otherwise been triggered).
 * @param array $DefineOptions An optional array containing key/value pairs,
 *      used to define configuration options specific to the request instance.
 *      Configuration options will be applied when the bypass is triggered.
 * @return bool Returns true if the bypass was triggered, and false if it
 *      wasn't. Should correspond to the truthiness of $Condition.
 */
$CIDRAM['Bypass'] = function ($Condition, $ReasonShort, $DefineOptions = array()) use (&$CIDRAM) {
    if (!$Condition) {
        return false;
    }
    if (is_array($DefineOptions) && !empty($DefineOptions)) {
        foreach ($DefineOptions as $CatKey => $CatValue) {
            if (is_array($CatValue) && !empty($CatValue)) {
                foreach ($CatValue as $OptionKey => $OptionValue) {
                    $CIDRAM['Config'][$CatKey][$OptionKey] = $OptionValue;
                }
            }
        }
    }
    if (!empty($CIDRAM['BlockInfo']['WhyReason'])) {
        $CIDRAM['BlockInfo']['WhyReason'] .= ', ';
    }
    $CIDRAM['BlockInfo']['WhyReason'] .= $ReasonShort;
    if (!empty($CIDRAM['BlockInfo']['Signatures'])) {
        $CIDRAM['BlockInfo']['Signatures'] .= ', ';
    }
    $Debug = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
    $CIDRAM['BlockInfo']['Signatures'] .= basename($Debug['file']) . ':L' . $Debug['line'];
    $CIDRAM['BlockInfo']['SignatureCount']--;
    return true;
};

/**
 * Used to generate new salts when necessary, which may be occasionally used by
 * some specific optional peripheral features (note: should not be considered
 * cryptographically secure; especially so for versions of PHP < 7).
 *
 * @return string Salt.
 */
$CIDRAM['GenerateSalt'] = function () {
    static $MinLen = 32;
    static $MaxLen = 72;
    static $MinChr = 1;
    static $MaxChr = 255;
    $Salt = '';
    if (function_exists('random_int')) {
        try {
            $Length = random_int($MinLen, $MaxLen);
        } catch (\Exception $e) {
            $Length = rand($MinLen, $MaxLen);
        }
    } else {
        $Length = rand($MinLen, $MaxLen);
    }
    if (function_exists('random_bytes')) {
        try {
            $Salt = random_bytes($Length);
        } catch (\Exception $e) {
            $Salt = '';
        }
    }
    if (empty($Salt)) {
        if (function_exists('random_int')) {
            try {
                for ($Index = 0; $Index < $Length; $Index++) {
                    $Salt .= chr(random_int($MinChr, $MaxChr));
                }
            } catch (\Exception $e) {
                $Salt = '';
                for ($Index = 0; $Index < $Length; $Index++) {
                    $Salt .= chr(rand($MinChr, $MaxChr));
                }
            }
        } else {
            for ($Index = 0; $Index < $Length; $Index++) {
                $Salt .= chr(rand($MinChr, $MaxChr));
            }
        }
    }
    return $Salt;
};

/**
 * Meld together two or more strings by padding to equal length and
 * bitshifting each by each other.
 *
 * @return string The melded string.
 */
$CIDRAM['Meld'] = function () {
    $Strings = func_get_args();
    $StrLens = array_map('strlen', $Strings);
    $WalkLen = max($StrLens);
    $Count = count($Strings);
    for ($Index = 0; $Index < $Count; $Index++) {
        if ($StrLens[$Index] < $WalkLen) {
            $Strings[$Index] = str_pad($Strings[$Index], $WalkLen, "\xff");
        }
    }
    for ($Lt = $Strings[0], $Index = 1, $Meld = ''; $Index < $Count; $Index++, $Meld = '') {
        $Rt = $Strings[$Index];
        for ($Caret = 0; $Caret < $WalkLen; $Caret++) {
            $Meld .= $Lt[$Caret] ^ $Rt[$Caret];
        }
        $Lt = $Meld;
    }
    $Meld = $Lt;
    return $Meld;
};

/**
 * Clears expired entries from sections of the "cache.dat" file and clears
 * empty sections.
 */
$CIDRAM['ClearFromCache'] = function ($Section) use (&$CIDRAM) {
    if (isset($CIDRAM['Cache'][$Section])) {
        foreach ($CIDRAM['Cache'][$Section] as $Key => $Value) {
            if ($Value['Time'] < $CIDRAM['Now']) {
                unset($CIDRAM['Cache'][$Section][$Key]);
                $CIDRAM['CacheModified'] = true;
            }
        }
        if (!count($CIDRAM['Cache'][$Section])) {
            unset($CIDRAM['Cache'][$Section]);
            $CIDRAM['CacheModified'] = true;
        }
    }
};

/** Clears expired entries from a list. */
$CIDRAM['ClearExpired'] = function (&$List, &$Check) use (&$CIDRAM) {
    if ($List) {
        $End = 0;
        while (true) {
            $Begin = $End;
            if (!$End = strpos($List, "\n", $Begin + 1)) {
                break;
            }
            $Line = substr($List, $Begin, $End - $Begin);
            if ($Split = strrpos($Line, ',')) {
                $Expiry = (int)substr($Line, $Split + 1);
                if ($Expiry < $CIDRAM['Now']) {
                    $List = str_replace($Line, '', $List);
                    $End = 0;
                    $Check = true;
                }
            }
        }
    }
};

/**
 * Adds integer values; Returns zero if the sum total is negative or if any
 * contained values aren't integers, and otherwise, returns the sum total.
 */
$CIDRAM['ZeroMin'] = function () {
    $Sum = 0;
    foreach (func_get_args() as $Value) {
        $IntValue = (int)$Value;
        if ($IntValue !== $Value) {
            return 0;
        }
        $Sum += $IntValue;
    }
    return $Sum < 0 ? 0 : $Sum;
};

/** Wrap state message for front-end. */
$CIDRAM['WrapRedText'] = function($Err) {
    return '<div class="txtRd">' . $Err . '<br /><br /></div>';
};

/** Format filesize information. */
$CIDRAM['FormatFilesize'] = function (&$Filesize) use (&$CIDRAM) {
    $Scale = array(
        $CIDRAM['lang']['field_size_bytes'],
        $CIDRAM['lang']['field_size_KB'],
        $CIDRAM['lang']['field_size_MB'],
        $CIDRAM['lang']['field_size_GB'],
        $CIDRAM['lang']['field_size_TB']
    );
    $Iterate = 0;
    $Filesize = (int)$Filesize;
    while ($Filesize > 1024) {
        $Filesize = $Filesize / 1024;
        $Iterate++;
        if ($Iterate > 4) {
            break;
        }
    }
    $Filesize = $CIDRAM['Number_L10N']($Filesize, ($Iterate === 0) ? 0 : 2) . ' ' . $Scale[$Iterate];
};

/**
 * Remove an entry from the front-end cache data.
 *
 * @param string $Source Variable containing cache file data.
 * @param bool $Rebuild Flag indicating to rebuild cache file.
 * @param string $Entry Name of the cache entry to be deleted.
 */
$CIDRAM['FECacheRemove'] = function (&$Source, &$Rebuild, $Entry) {
    $Entry64 = base64_encode($Entry);
    while (($EntryPos = strpos($Source, "\n" . $Entry64 . ',')) !== false) {
        $EoL = strpos($Source, "\n", $EntryPos + 1);
        if ($EoL !== false) {
            $Line = substr($Source, $EntryPos, $EoL - $EntryPos);
            $Source = str_replace($Line, '', $Source);
            $Rebuild = true;
        }
    }
};

/**
 * Add an entry to the front-end cache data.
 *
 * @param string $Source Variable containing cache file data.
 * @param bool $Rebuild Flag indicating to rebuild cache file.
 * @param string $Entry Name of the cache entry to be added.
 * @param string $Data Cache entry data (what should be cached).
 * @param int $Expires When should the cache entry expire (be deleted).
 */
$CIDRAM['FECacheAdd'] = function (&$Source, &$Rebuild, $Entry, $Data, $Expires) use (&$CIDRAM) {
    $CIDRAM['FECacheRemove']($Source, $Rebuild, $Entry);
    $Expires = (int)$Expires;
    $NewLine = base64_encode($Entry) . ',' . base64_encode($Data) . ',' . $Expires . "\n";
    $Source .= $NewLine;
    $Rebuild = true;
};

/**
 * Get an entry from the front-end cache data.
 *
 * @param string $Source Variable containing cache file data.
 * @param bool $Rebuild Flag indicating to rebuild cache file.
 * @param string $Entry Name of the cache entry to get.
 * return string|bool Returned cache entry data (or false on failure).
 */
$CIDRAM['FECacheGet'] = function ($Source, $Entry) {
    $Entry = base64_encode($Entry);
    $EntryPos = strpos($Source, "\n" . $Entry . ',');
    if ($EntryPos !== false) {
        $EoL = strpos($Source, "\n", $EntryPos + 1);
        if ($EoL !== false) {
            $Line = substr($Source, $EntryPos, $EoL - $EntryPos);
            $Entry = explode(',', $Line);
            if (!empty($Entry[1])) {
                return base64_decode($Entry[1]);
            }
        }
    }
    return false;
};

/**
 * Compare two different versions of CIDRAM, or two different versions of a
 * component for CIDRAM, to see which is newer (mostly used by the updater).
 *
 * @param string $A The 1st version string.
 * @param string $B The 2nd version string.
 * return bool True if the 2nd version is newer than the 1st version, and false
 *      otherwise (i.e., if they're the same, or if the 1st version is newer).
 */
$CIDRAM['VersionCompare'] = function ($A, $B) {
    $Normalise = function (&$Ver) {
        $Ver =
            preg_match('~^v?([0-9]+)$~i', $Ver, $Matches) ?:
            preg_match('~^v?([0-9]+)\.([0-9]+)$~i', $Ver, $Matches) ?:
            preg_match('~^v?([0-9]+)\.([0-9]+)\.([0-9]+)(RC[0-9]{1,2}|-[0-9a-z_+\\/]+)?$~i', $Ver, $Matches) ?:
            preg_match('~^([0-9]{1,4})[.-]([0-9]{1,2})[.-]([0-9]{1,4})(RC[0-9]{1,2}|[.+-][0-9a-z_+\\/]+)?$~i', $Ver, $Matches) ?:
            preg_match('~^([a-z]+)-([0-9a-z]+)-([0-9a-z]+)$~i', $Ver, $Matches);
        $Ver = array(
            'Major' => isset($Matches[1]) ? $Matches[1] : 0,
            'Minor' => isset($Matches[2]) ? $Matches[2] : 0,
            'Patch' => isset($Matches[3]) ? $Matches[3] : 0,
            'Build' => isset($Matches[4]) ? substr($Matches[4], 1) : 0
        );
        $Ver = array_map(function ($Var) {
            $VarInt = (int)$Var;
            $VarLen = strlen($Var);
            if ($Var == $VarInt && strlen($VarInt) === $VarLen && $VarLen > 1) {
                return $VarInt;
            }
            return strtolower($Var);
        }, $Ver);
    };
    $Normalise($A);
    $Normalise($B);
    return (
        $B['Major'] > $A['Major'] || (
            $B['Major'] === $A['Major'] &&
            $B['Minor'] > $A['Minor']
        ) || (
            $B['Major'] === $A['Major'] &&
            $B['Minor'] === $A['Minor'] &&
            $B['Patch'] > $A['Patch']
        ) || (
            $B['Major'] === $A['Major'] &&
            $B['Minor'] === $A['Minor'] &&
            $B['Patch'] === $A['Patch'] &&
            !empty($A['Build']) && (
                empty($B['Build']) || $B['Build'] > $A['Build']
            )
        )
    );
};

/**
 * Remove sub-arrays from an array.
 *
 * @param array $Arr An array.
 * return array An array.
 */
$CIDRAM['ArrayFlatten'] = function ($Arr) {
    return array_filter($Arr, function () {
        return (!is_array(func_get_args()[0]));
    });
};

/** Isolate a L10N array down to a single relevant L10N string. */
$CIDRAM['IsolateL10N'] = function (&$Arr, $Lang) {
    if (isset($Arr[$Lang])) {
        $Arr = $Arr[$Lang];
    } elseif (isset($Arr['en'])) {
        $Arr = $Arr['en'];
    } else {
        $Key = key($Arr);
        $Arr = $Arr[$Key];
    }
};

/**
 * Append one or two values to a string, depending on whether that string is
 * empty prior to calling the closure (allows cleaner code in some areas).
 *
 * @param string $String The string to work with.
 * @param string $Delimit Appended first, if the string is not empty.
 * @param string $Append Appended second, and always (empty or otherwise).
 */
$CIDRAM['AppendToString'] = function (&$String, $Delimit = '', $Append = '') {
    if (!empty($String)) {
        $String .= $Delimit;
    }
    $String .= $Append;
};

/** If input isn't an array, make it so. Remove empty elements. */
$CIDRAM['Arrayify'] = function (&$Input) {
    if (!is_array($Input)) {
        $Input = array($Input);
    }
    $Input = array_filter($Input);
};

/**
 * Used by the file manager to generate a list of the files contained in a
 * working directory (normally, the vault).
 *
 * @param string $Base The path to the working directory.
 * @return array A list of the files contained in the working directory.
 */
$CIDRAM['FileManager-RecursiveList'] = function ($Base) use (&$CIDRAM) {
    $Arr = array();
    $Key = -1;
    $Offset = strlen($Base);
    $List = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($Base), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($List as $Item => $List) {
        $Key++;
        $ThisName = substr($Item, $Offset);
        if (preg_match('~^(?:/\.\.|./\.|\.{3})$~', str_replace("\\", '/', substr($Item, -3)))) {
            continue;
        }
        $Arr[$Key] = array('Filename' => $ThisName);
        if (is_dir($Item)) {
            $Arr[$Key]['CanEdit'] = false;
            $Arr[$Key]['Directory'] = true;
            $Arr[$Key]['Filesize'] = 0;
            $Arr[$Key]['Filetype'] = $CIDRAM['lang']['field_filetype_directory'];
            $Arr[$Key]['Icon'] = 'icon=directory';
        } elseif (is_file($Item)) {
            $Arr[$Key]['CanEdit'] = true;
            $Arr[$Key]['Directory'] = false;
            $Arr[$Key]['Filesize'] = filesize($Item);
            if (isset($CIDRAM['FE']['TotalSize'])) {
                $CIDRAM['FE']['TotalSize'] += $Arr[$Key]['Filesize'];
            }
            if (isset($CIDRAM['Components']['Components'])) {
                $Component = $CIDRAM['lang']['field_filetype_unknown'];
                $ThisNameFixed = str_replace("\\", '/', $ThisName);
                if (isset($CIDRAM['Components']['Files'][$ThisNameFixed])) {
                    if (!empty($CIDRAM['Components']['Names'][$CIDRAM['Components']['Files'][$ThisNameFixed]])) {
                        $Component = $CIDRAM['Components']['Names'][$CIDRAM['Components']['Files'][$ThisNameFixed]];
                    } else {
                        $Component = $CIDRAM['Components']['Files'][$ThisNameFixed];
                    }
                    if ($Component === 'CIDRAM') {
                        $Component .= ' (' . $CIDRAM['lang']['field_component'] . ')';
                    }
                } elseif (substr($ThisNameFixed, -10) === 'config.ini') {
                    $Component = $CIDRAM['lang']['link_config'];
                } else {
                    $LastFour = strtolower(substr($ThisNameFixed, -4));
                    if (
                        $LastFour === '.tmp' ||
                        $ThisNameFixed === 'cache.dat' ||
                        $ThisNameFixed === 'fe_assets/frontend.dat' ||
                        substr($ThisNameFixed, -9) === '.rollback'
                    ) {
                        $Component = $CIDRAM['lang']['label_fmgr_cache_data'];
                    } elseif ($LastFour === '.log' || $LastFour === '.txt') {
                        $Component = $CIDRAM['lang']['link_logs'];
                    } elseif (preg_match('/^\.(?:dat|inc|ya?ml)$/i', $LastFour)) {
                        $Component = $CIDRAM['lang']['label_fmgr_updates_metadata'];
                    }
                }
                if (!isset($CIDRAM['Components']['Components'][$Component])) {
                    $CIDRAM['Components']['Components'][$Component] = 0;
                }
                $CIDRAM['Components']['Components'][$Component] += $Arr[$Key]['Filesize'];
            }
            if (($ExtDel = strrpos($Item, '.')) !== false) {
                $Ext = strtoupper(substr($Item, $ExtDel + 1));
                if (!$Ext) {
                    $Arr[$Key]['Filetype'] = $CIDRAM['lang']['field_filetype_unknown'];
                    $Arr[$Key]['Icon'] = 'icon=unknown';
                    $CIDRAM['FormatFilesize']($Arr[$Key]['Filesize']);
                    continue;
                }
                $Arr[$Key]['Filetype'] = $CIDRAM['ParseVars'](array('EXT' => $Ext), $CIDRAM['lang']['field_filetype_info']);
                if ($Ext === 'ICO') {
                    $Arr[$Key]['Icon'] = 'file=' . urlencode($Prepend . $Item);
                    $CIDRAM['FormatFilesize']($Arr[$Key]['Filesize']);
                    continue;
                }
                if (preg_match(
                    '/^(?:.?[BGL]Z.?|7Z|A(CE|LZ|P[KP]|R[CJ]?)?|B([AH]|Z2?)|CAB|DMG|' .
                    'I(CE|SO)|L(HA|Z[HOWX]?)|P(AK|AQ.?|CK|EA)|RZ|S(7Z|EA|EN|FX|IT.?|QX)|' .
                    'X(P3|Z)|YZ1|Z(IP.?|Z)?|(J|M|PH|R|SH|T|X)AR)$/'
                , $Ext)) {
                    $Arr[$Key]['CanEdit'] = false;
                    $Arr[$Key]['Icon'] = 'icon=archive';
                } elseif (preg_match('/^[SDX]?HT[AM]L?$/', $Ext)) {
                    $Arr[$Key]['Icon'] = 'icon=html';
                } elseif (preg_match('/^(?:CSV|JSON|NEON|SQL|YAML)$/', $Ext)) {
                    $Arr[$Key]['Icon'] = 'icon=ods';
                } elseif (preg_match('/^(?:PDF|XDP)$/', $Ext)) {
                    $Arr[$Key]['CanEdit'] = false;
                    $Arr[$Key]['Icon'] = 'icon=pdf';
                } elseif (preg_match('/^DOC[XT]?$/', $Ext)) {
                    $Arr[$Key]['CanEdit'] = false;
                    $Arr[$Key]['Icon'] = 'icon=doc';
                } elseif (preg_match('/^XLS[XT]?$/', $Ext)) {
                    $Arr[$Key]['CanEdit'] = false;
                    $Arr[$Key]['Icon'] = 'icon=xls';
                } elseif (preg_match('/^(?:CSS|JS|OD[BFGPST]|P(HP|PT))$/', $Ext)) {
                    $Arr[$Key]['Icon'] = 'icon=' . strtolower($Ext);
                    if (!preg_match('/^(?:CSS|JS|PHP)$/', $Ext)) {
                        $Arr[$Key]['CanEdit'] = false;
                    }
                } elseif (preg_match('/^(?:FLASH|SWF)$/', $Ext)) {
                    $Arr[$Key]['CanEdit'] = false;
                    $Arr[$Key]['Icon'] = 'icon=swf';
                } elseif (preg_match(
                    '/^(?:BM[2P]|C(D5|GM)|D(IB|W[FG]|XF)|ECW|FITS|GIF|IMG|J(F?IF?|P[2S]|PE?G?2?|XR)|P(BM|CX|DD|GM|IC|N[GMS]|PM|S[DP])|S(ID|V[AG])|TGA|W(BMP?|EBP|MP)|X(CF|BMP))$/'
                , $Ext)) {
                    $Arr[$Key]['CanEdit'] = false;
                    $Arr[$Key]['Icon'] = 'icon=image';
                } elseif (preg_match(
                    '/^(?:H?264|3GP(P2)?|A(M[CV]|VI)|BIK|D(IVX|V5?)|F([4L][CV]|MV)|GIFV|HLV|' .
                    'M(4V|OV|P4|PE?G[4V]?|KV|VR)|OGM|V(IDEO|OB)|W(EBM|M[FV]3?)|X(WMV|VID))$/'
                , $Ext)) {
                    $Arr[$Key]['CanEdit'] = false;
                    $Arr[$Key]['Icon'] = 'icon=video';
                } elseif (preg_match(
                    '/^(?:3GA|A(AC|IFF?|SF|U)|CDA|FLAC?|M(P?4A|IDI|KA|P[A23])|OGG|PCM|' .
                    'R(AM?|M[AX])|SWA|W(AVE?|MA))$/'
                , $Ext)) {
                    $Arr[$Key]['CanEdit'] = false;
                    $Arr[$Key]['Icon'] = 'icon=audio';
                } elseif (preg_match('/^(?:MD|NFO|RTF|TXT)$/', $Ext)) {
                    $Arr[$Key]['Icon'] = 'icon=text';
                }
            } else {
                $Arr[$Key]['Filetype'] = $CIDRAM['lang']['field_filetype_unknown'];
            }
        }
        if (empty($Arr[$Key]['Icon'])) {
            $Arr[$Key]['Icon'] = 'icon=unknown';
        }
        if ($Arr[$Key]['Filesize']) {
            $CIDRAM['FormatFilesize']($Arr[$Key]['Filesize']);
        } else {
            $Arr[$Key]['Filesize'] = '';
        }
    }
    return $Arr;
};

/**
 * Used by the file manager and the updates pages to fetch the components list.
 *
 * @param string $Base The path to the working directory.
 * @param array $Arr The array to use for rendering components file YAML data.
 */
$CIDRAM['FetchComponentsLists'] = function ($Base, &$Arr) use (&$CIDRAM) {
    $Files = new DirectoryIterator($Base);
    foreach ($Files as $ThisFile) {
        if (!empty($ThisFile) && preg_match('/\.(?:dat|inc|ya?ml)$/i', $ThisFile)) {
            $Data = $CIDRAM['ReadFile']($Base . $ThisFile);
            if (substr($Data, 0, 4) === "---\n" && ($EoYAML = strpos($Data, "\n\n")) !== false) {
                $CIDRAM['YAML'](substr($Data, 4, $EoYAML - 4), $Arr);
            }
        }
    }
};

/**
 * Checks paths for directory traversal and ensures that they only contain
 * expected characters.
 *
 * @param string $Path The path to check.
 * @return bool False when directory traversals and/or unexpected characters
 *      are detected, and true otherwise.
 */
$CIDRAM['FileManager-PathSecurityCheck'] = function ($Path) {
    $Path = str_replace("\\", '/', $Path);
    if (
        preg_match('~(?://|[^!0-9A-Za-z\._-]$)~', $Path) ||
        preg_match('~^(?:/\.\.|./\.|\.{3})$~', str_replace("\\", '/', substr($Path, -3)))
    ) {
        return false;
    }
    $Path = preg_split('@/@', $Path, -1, PREG_SPLIT_NO_EMPTY);
    $Valid = true;
    array_walk($Path, function($Segment) use (&$Valid) {
        if (empty($Segment) || preg_match('/(?:[\x00-\x1f\x7f]+|^\.+$)/i', $Segment)) {
            $Valid = false;
        }
    });
    return $Valid;
};

/**
 * Checks whether the specified directory is empty.
 *
 * @param string $Directory The directory to check.
 * @return bool True if empty; False if not empty.
 */
$CIDRAM['FileManager-IsDirEmpty'] = function ($Directory) {
    return !((new \FilesystemIterator($Directory))->valid());
};

/**
 * Used by the logs viewer to generate a list of the logfiles contained in a
 * working directory (normally, the vault).
 *
 * @param string $Base The path to the working directory.
 * @return array A list of the logfiles contained in the working directory.
 */
$CIDRAM['Logs-RecursiveList'] = function ($Base) use (&$CIDRAM) {
    $Arr = array();
    $List = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($Base), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($List as $Item => $List) {
        if (
            preg_match('~^(?:/\.\.|./\.|\.{3})$~', str_replace("\\", '/', substr($Item, -3))) ||
            !preg_match('~(?:logfile|\.(txt|log)$)~i', $Item) ||
            !file_exists($Item) ||
            is_dir($Item) ||
            !is_file($Item) ||
            !is_readable($Item)
        ) {
            continue;
        }
        $ThisName = substr($Item, strlen($Base));
        $Arr[$ThisName] = array('Filename' => $ThisName, 'Filesize' => filesize($Item));
        $CIDRAM['FormatFilesize']($Arr[$ThisName]['Filesize']);
    }
    return $Arr;
};

/**
 * Checks whether a component is in use (front-end closure).
 *
 * @param array $Files The list of files to be checked.
 * @param array $Files The component extended description (used to determine
 *      which type of component it is).
 * @return bool Returns true (in use) or false (not in use).
 */
$CIDRAM['IsInUse'] = function ($Files, $Description) use (&$CIDRAM) {
    foreach ($Files as $File) {
        if ((
            strpos($Description, 'signatures-&gt;ipv4') !== false &&
            strpos(',' . $CIDRAM['Config']['signatures']['ipv4'] . ',', ',' . $File . ',') !== false
        ) || (
            strpos($Description, 'signatures-&gt;ipv6') !== false &&
            strpos(',' . $CIDRAM['Config']['signatures']['ipv6'] . ',', ',' . $File . ',') !== false
        ) || (
            strpos($Description, 'signatures-&gt;modules') !== false &&
            strpos(',' . $CIDRAM['Config']['signatures']['modules'] . ',', ',' . $File . ',') !== false
        ) || (
            strpos($Description, 'signatures-&gt;ipv4') === false &&
            strpos($Description, 'signatures-&gt;ipv6') === false &&
            strpos($Description, 'signatures-&gt;modules') === false && (
                strpos(',' . $CIDRAM['Config']['signatures']['ipv4'] . ',', ',' . $File . ',') !== false ||
                strpos(',' . $CIDRAM['Config']['signatures']['ipv6'] . ',', ',' . $File . ',') !== false ||
                strpos(',' . $CIDRAM['Config']['signatures']['modules'] . ',', ',' . $File . ',') !== false
            )
        )) {
            return true;
        }
    }
    return false;
};

/**
 * Determine the final IP address covered by an IPv4 CIDR. This closure is used
 * by the CIDR Calculator.
 *
 * @param string $First The first IP address.
 * @param int $Factor The range number (or CIDR factor number).
 * @return string The final IP address.
 */
$CIDRAM['IPv4GetLast'] = function ($First, $Factor) {
    $Octets = explode('.', $First);
    $Split = $Bracket = $Factor / 8;
    $Split -= floor($Split);
    $Split = (int)(8 - ($Split * 8));
    $Octet = floor($Bracket);
    if ($Octet < 4) {
        $Octets[$Octet] += pow(2, $Split) - 1;
    }
    while ($Octet < 3) {
        $Octets[$Octet + 1] = 255;
        $Octet++;
    }
    return implode('.', $Octets);
};

/**
 * Determine the final IP address covered by an IPv6 CIDR. This closure is used
 * by the CIDR Calculator.
 *
 * @param string $First The first IP address.
 * @param int $Factor The range number (or CIDR factor number).
 * @return string The final IP address.
 */
$CIDRAM['IPv6GetLast'] = function ($First, $Factor) {
    if (substr_count($First, '::')) {
        $Abr = 7 - substr_count($First, ':');
        $Arr = array(':0:', ':0:0:', ':0:0:0:', ':0:0:0:0:', ':0:0:0:0:0:', ':0:0:0:0:0:0:');
        $First = str_replace('::', $Arr[$Abr], $First);
    }
    $Octets = explode(':', $First);
    $Octet = 8;
    while ($Octet > 0) {
        $Octet--;
        $Octets[$Octet] = hexdec($Octets[$Octet]);
    }
    $Split = $Bracket = $Factor / 16;
    $Split -= floor($Split);
    $Split = (int)(16 - ($Split * 16));
    $Octet = floor($Bracket);
    if ($Octet < 8) {
        $Octets[$Octet] += pow(2, $Split) - 1;
    }
    while ($Octet < 7) {
        $Octets[$Octet + 1] = 65535;
        $Octet++;
    }
    $Octet = 8;
    while ($Octet > 0) {
        $Octet--;
        $Octets[$Octet] = dechex($Octets[$Octet]);
    }
    $Last = implode(':', $Octets);
    if (strpos($Last . '/', ':0:0/') !== false) {
        $Last = preg_replace('/(\:0){2,}$/i', '::', $Last, 1);
    } elseif (strpos($Last, ':0:0:') !== false) {
        $Last = preg_replace('/(?:(\:0)+\:(0\:)+|\:\:0$)/i', '::', $Last, 1);
    }
    return $Last;
};

/** Fetch remote data (front-end updates page). */
$CIDRAM['FetchRemote'] = function () use (&$CIDRAM) {
    $CIDRAM['Components']['ThisComponent']['RemoteData'] = $CIDRAM['FECacheGet'](
        $CIDRAM['FE']['Cache'],
        $CIDRAM['Components']['ThisComponent']['Remote']
    );
    if (!$CIDRAM['Components']['ThisComponent']['RemoteData']) {
        $CIDRAM['Components']['ThisComponent']['RemoteData'] = $CIDRAM['Request']($CIDRAM['Components']['ThisComponent']['Remote']);
        if (
            strtolower(substr($CIDRAM['Components']['ThisComponent']['Remote'], -2)) === 'gz' &&
            substr($CIDRAM['Components']['ThisComponent']['RemoteData'], 0, 2) === "\x1f\x8b"
        ) {
            $CIDRAM['Components']['ThisComponent']['RemoteData'] = gzdecode($CIDRAM['Components']['ThisComponent']['RemoteData']);
        }
        if (empty($CIDRAM['Components']['ThisComponent']['RemoteData'])) {
            $CIDRAM['Components']['ThisComponent']['RemoteData'] = '-';
        }
        $CIDRAM['FECacheAdd'](
            $CIDRAM['FE']['Cache'],
            $CIDRAM['FE']['Rebuild'],
            $CIDRAM['Components']['ThisComponent']['Remote'],
            $CIDRAM['Components']['ThisComponent']['RemoteData'],
            $CIDRAM['Now'] + 3600
        );
    }
};

/** Activate component (front-end updates page). */
$CIDRAM['ActivateComponent'] = function ($Type) use (&$CIDRAM) {
    $CIDRAM['Activation'][$Type] = array_unique(array_filter(
        explode(',', $CIDRAM['Activation'][$Type]),
        function ($Component) use (&$CIDRAM) {
            return ($Component && file_exists($CIDRAM['Vault'] . $Component));
        }
    ));
    foreach ($CIDRAM['Components']['Meta'][$_POST['ID']]['Files']['To'] as $CIDRAM['Activation']['ThisFile']) {
        if (
            !empty($CIDRAM['Activation']['ThisFile']) &&
            file_exists($CIDRAM['Vault'] . $CIDRAM['Activation']['ThisFile']) &&
            $CIDRAM['Traverse']($CIDRAM['Activation']['ThisFile'])
        ) {
            $CIDRAM['Activation'][$Type][] = $CIDRAM['Activation']['ThisFile'];
        }
    }
    if (count($CIDRAM['Activation'][$Type])) {
        sort($CIDRAM['Activation'][$Type]);
    }
    $CIDRAM['Activation'][$Type] = implode(',', $CIDRAM['Activation'][$Type]);
    if ($CIDRAM['Activation'][$Type] !== $CIDRAM['Config']['signatures'][$Type]) {
        $CIDRAM['Activation']['modified'] = true;
    }
};

/** Deactivate component (front-end updates page). */
$CIDRAM['DeactivateComponent'] = function ($Type) use (&$CIDRAM) {
    $CIDRAM['Deactivation'][$Type] = array_unique(array_filter(
        explode(',', $CIDRAM['Deactivation'][$Type]),
        function ($Component) use (&$CIDRAM) {
            return ($Component && file_exists($CIDRAM['Vault'] . $Component));
        }
    ));
    if (count($CIDRAM['Deactivation'][$Type])) {
        sort($CIDRAM['Deactivation'][$Type]);
    }
    $CIDRAM['Deactivation'][$Type] = ',' . implode(',', $CIDRAM['Deactivation'][$Type]) . ',';
    foreach ($CIDRAM['Components']['Meta'][$_POST['ID']]['Files']['To'] as $CIDRAM['Deactivation']['ThisFile']) {
        $CIDRAM['Deactivation'][$Type] =
            str_replace(',' . $CIDRAM['Deactivation']['ThisFile'] . ',', ',', $CIDRAM['Deactivation'][$Type]);
    }
    $CIDRAM['Deactivation'][$Type] = substr($CIDRAM['Deactivation'][$Type], 1, -1);
    if ($CIDRAM['Deactivation'][$Type] !== $CIDRAM['Config']['signatures'][$Type]) {
        $CIDRAM['Deactivation']['modified'] = true;
    }
};

/** Prepares component extended description (front-end updates page). */
$CIDRAM['PrepareExtendedDescription'] = function (&$Arr, $Key = '') use (&$CIDRAM) {
    $Key = 'Extended Description: ' . $Key;
    if (isset($CIDRAM['lang'][$Key])) {
        $Arr['Extended Description'] = $CIDRAM['lang'][$Key];
    } elseif (empty($Arr['Extended Description'])) {
        $Arr['Extended Description'] = '';
    }
    if (is_array($Arr['Extended Description'])) {
        $CIDRAM['IsolateL10N']($Arr['Extended Description'], $CIDRAM['Config']['general']['lang']);
    }
    if (!empty($Arr['False Positive Risk'])) {
        if ($Arr['False Positive Risk'] === 'Low') {
            $State = $CIDRAM['lang']['state_risk_low'];
            $Class = 'txtGn';
        } elseif ($Arr['False Positive Risk'] === 'Medium') {
            $State = $CIDRAM['lang']['state_risk_medium'];
            $Class = 'txtOe';
        } elseif ($Arr['False Positive Risk'] === 'High') {
            $State = $CIDRAM['lang']['state_risk_high'];
            $Class = 'txtRd';
        } else {
            return;
        }
        $Arr['Extended Description'] .=
            '<br /><em>' . $CIDRAM['lang']['label_false_positive_risk'] .
            '<span class="' . $Class . '">' . $State . '</span></em>';
    }
};

/** Prepares component name (front-end updates page). */
$CIDRAM['PrepareName'] = function (&$Arr, $Key = '') use (&$CIDRAM) {
    $Key = 'Name: ' . $Key;
    if (isset($CIDRAM['lang'][$Key])) {
        $Arr['Name'] = $CIDRAM['lang'][$Key];
    } elseif (empty($Arr['Name'])) {
        $Arr['Name'] = '';
    }
    if (is_array($Arr['Name'])) {
        $CIDRAM['IsolateL10N']($Arr['Name'], $CIDRAM['Config']['general']['lang']);
    }
};

/** Duplication avoidance (front-end updates page). */
$CIDRAM['ComponentFunctionUpdatePrep'] = function () use (&$CIDRAM) {
    if (!empty($CIDRAM['Components']['Meta'][$_POST['ID']]['Files'])) {
        $CIDRAM['PrepareExtendedDescription']($CIDRAM['Components']['Meta'][$_POST['ID']]);
        $CIDRAM['Arrayify']($CIDRAM['Components']['Meta'][$_POST['ID']]['Files']);
        $CIDRAM['Arrayify']($CIDRAM['Components']['Meta'][$_POST['ID']]['Files']['To']);
        $CIDRAM['Components']['Meta'][$_POST['ID']]['Files']['InUse'] = $CIDRAM['IsInUse'](
            $CIDRAM['Components']['Meta'][$_POST['ID']]['Files']['To'],
            $CIDRAM['Components']['Meta'][$_POST['ID']]['Extended Description']
        );
    }
};

/** Duplication avoidance (front-end IP test page and IP tracking page). */
$CIDRAM['SimulateBlockEvent'] = function ($Addr) use (&$CIDRAM) {
    $CIDRAM['BlockInfo'] = array(
        'IPAddr' => $Addr,
        'Query' => 'SimulateBlockEvent',
        'Referrer' => '',
        'UA' => '',
        'UALC' => '',
        'ReasonMessage' => '',
        'SignatureCount' => 0,
        'Signatures' => '',
        'WhyReason' => '',
        'xmlLang' => $CIDRAM['Config']['general']['lang'],
        'rURI' => 'SimulateBlockEvent'
    );
    try {
        $CIDRAM['Caught'] = false;
        $CIDRAM['TestResults'] = $CIDRAM['RunTests']($Addr);
    } catch (\Exception $e) {
        $CIDRAM['Caught'] = true;
    }
};

/**
 * Read byte value configuration directives as byte values.
 *
 * @param string $In Input.
 * @param int $Mode Operating mode. 0 for true byte values, 1 for validating.
 *      Default is 0.
 * @return string|int Output (depends on operating mode).
 */
$CIDRAM['ReadBytes'] = function ($In, $Mode = 0) {
    if (preg_match('/[KMGT][oB]$/i', $In)) {
        $Unit = substr($In, -2, 1);
    } elseif (preg_match('/[KMGToB]$/i', $In)) {
        $Unit = substr($In, -1);
    }
    $Unit = isset($Unit) ? strtoupper($Unit) : 'K';
    $In = (real)$In;
    if ($Mode === 1) {
        return $Unit === 'B' || $Unit === 'o' ? $In . 'B' : $In . $Unit . 'B';
    }
    $Multiply = array('K' => 1024, 'M' => 1048576, 'G' => 1073741824, 'T' => 1099511627776);
    return (int)floor($In * (isset($Multiply[$Unit]) ? $Multiply[$Unit] : 1));
};

/**
 * Filter the available language options provided by the configuration page on
 * the basis of the availability of the corresponding language files.
 *
 * @param string $ChoiceKey Language code.
 * @return bool Valid/Invalid.
 */
$CIDRAM['FilterLang'] = function ($ChoiceKey) use (&$CIDRAM) {
    $Path = $CIDRAM['Vault'] . 'lang/lang.' . $ChoiceKey;
    return (file_exists($Path . '.php') && file_exists($Path . '.fe.php'));
};

/**
 * Filter the available hash algorithms provided by the configuration page on
 * the basis of their availability.
 *
 * @param string $ChoiceKey Hash algorithm.
 * @return bool Valid/Invalid.
 */
$CIDRAM['FilterAlgo'] = function ($ChoiceKey) use (&$CIDRAM) {
    return ($ChoiceKey === 'PASSWORD_ARGON2I') ? !$CIDRAM['VersionCompare'](PHP_VERSION, '7.2.0RC1') : true;
};

/**
 * Filter the available theme options provided by the configuration page on
 * the basis of their availability.
 *
 * @param string $ChoiceKey Theme ID.
 * @return bool Valid/Invalid.
 */
$CIDRAM['FilterTheme'] = function ($ChoiceKey) use (&$CIDRAM) {
    if ($ChoiceKey === 'default') {
        return true;
    }
    $Path = $CIDRAM['Vault'] . 'fe_assets/' . $ChoiceKey . '/';
    return (file_exists($Path . 'frontend.css') || file_exists($CIDRAM['Vault'] . 'template_' . $ChoiceKey . '.html'));
};

/** Attempt to perform some simple formatting for the log data. */
$CIDRAM['Formatter'] = function (&$In) {
    $Len = strlen($In);
    if ($Len > 65536 || $Len > ini_get('pcre.backtrack_limit')) {
        return;
    }
    preg_match_all('~(&lt;\?.*\?&gt;|<\?.*\?>|\{.*\})~i', $In, $Parts);
    foreach ($Parts[0] as $ThisPart) {
        if (strlen($ThisPart) > 512 || strpos($ThisPart, "\n") !== false) {
            continue;
        }
        $In = str_replace($ThisPart, '<code>' . $ThisPart . '</code>', $In);
    }
    if (strpos($In, "<br />\n<br />\n") !== false) {
        preg_match_all('~\n([^\n:]+): [^\n]+~i', $In, $Parts);
        foreach ($Parts[1] as $ThisPart) {
            $In = str_replace("\n" . $ThisPart . ': ', "\n<span class=\"textLabel\">" . $ThisPart . '</span>: ', $In);
        }
        preg_match_all('~\n([^\n:]+): [^\n]+~i', $In, $Parts);
        foreach ($Parts[0] as $ThisPart) {
            $In = str_replace("\n" . substr($ThisPart, 1) . "\n", "\n<span class=\"s\">" . substr($ThisPart, 1) . "</span>\n", $In);
        }
    }
};

/**
 * Get the appropriate path for a specified asset as per the defined theme.
 *
 * @param string $Asset The asset filename.
 * @param bool $CanFail Is failure acceptable? (Default: False)
 * @return string The asset path.
 */
$CIDRAM['GetAssetPath'] = function ($Asset, $CanFail = false) use (&$CIDRAM) {
    if (
        $CIDRAM['Config']['template_data']['theme'] !== 'default' &&
        file_exists($CIDRAM['Vault'] . 'fe_assets/' . $CIDRAM['Config']['template_data']['theme'] . '/' . $Asset)
    ) {
        return $CIDRAM['Vault'] . 'fe_assets/' . $CIDRAM['Config']['template_data']['theme'] . '/' . $Asset;
    }
    if (file_exists($CIDRAM['Vault'] . 'fe_assets/' . $Asset)) {
        return $CIDRAM['Vault'] . 'fe_assets/' . $Asset;
    }
    if ($CanFail) {
        return '';
    }
    throw new \Exception('Asset not found');
};

/**
 * Determines whether to display warnings about the PHP version used (based
 * upon what we know at the time that the package was last updated; information
 * herein is likely to become stale very quickly when not updated frequently).
 *
 * References:
 * - secure.php.net/releases/
 * - secure.php.net/supported-versions.php
 * - cvedetails.com/vendor/74/PHP.html
 * - maikuolan.github.io/Compatibility-Charts/
 * - maikuolan.github.io/Vulnerability-Charts/php.html
 *
 * @param string $Version The PHP version used (defaults to PHP_VERSION).
 * return int Warning level.
 */
$CIDRAM['VersionWarning'] = function ($Version = PHP_VERSION) use (&$CIDRAM) {
    $Date = date('Y.n.j', $CIDRAM['Now']);
    $Level = 0;
    if (!empty($CIDRAM['ForceVersionWarning']) || $CIDRAM['VersionCompare']($Version, '5.6.31') || (
        !$CIDRAM['VersionCompare']($Version, '7.0.0') && $CIDRAM['VersionCompare']($Version, '7.0.17')
    ) || (
        !$CIDRAM['VersionCompare']($Version, '7.1.0') && $CIDRAM['VersionCompare']($Version, '7.1.3')
    )) {
        $Level += 2;
    }
    if ($CIDRAM['VersionCompare']($Version, '7.0.0') || (
        !$CIDRAM['VersionCompare']($Date, '2017.12.3') && $CIDRAM['VersionCompare']($Version, '7.1.0')
    ) || (
        !$CIDRAM['VersionCompare']($Date, '2018.12.1') && $CIDRAM['VersionCompare']($Version, '7.2.0')
    )) {
        $Level += 1;
    }
    $CIDRAM['ForceVersionWarning'] = false;
    return $Level;
};

/**
 * Executes a list of closures or commands when specific conditions are met.
 *
 * @param array|string $Closures The list of closures or commands to execute.
 */
$CIDRAM['FE_Executor'] = function ($Closures) use (&$CIDRAM) {
    $CIDRAM['Arrayify']($Closures);
    foreach ($Closures as $Closure) {
        if (isset($CIDRAM[$Closure]) && is_object($CIDRAM[$Closure])) {
            $CIDRAM[$Closure]();
        } elseif (($Pos = strpos($Closure, ' ')) !== false) {
            $Params = substr($Closure, $Pos + 1);
            $Closure = substr($Closure, 0, $Pos);
            if (isset($CIDRAM[$Closure]) && is_object($CIDRAM[$Closure])) {
                $CIDRAM[$Closure]($Params);
            }
        }
    }
};

/**
 * Updates plugin version cited in the WordPress plugins dashboard, if this
 * copy of CIDRAM is running as a WordPress plugin.
 */
$CIDRAM['WP-Ver'] = function () use (&$CIDRAM) {
    if (
        file_exists($CIDRAM['Vault'] . '../cidram.php') &&
        is_readable($CIDRAM['Vault'] . '../cidram.php') &&
        !empty($CIDRAM['Components']['RemoteMeta'][$CIDRAM['Components']['ThisTarget']]['Version']) &&
        ($ThisData = $CIDRAM['ReadFile']($CIDRAM['Vault'] . '../cidram.php'))
    ) {
        $PlugHead = "\x3C\x3Fphp\n/**\n * Plugin Name: CIDRAM\n * Version: ";
        if (substr($ThisData, 0, 45) === $PlugHead) {
            $PlugHeadEnd = strpos($ThisData, "\n", 45);
            $ThisData =
                $PlugHead .
                $CIDRAM['Components']['RemoteMeta'][$CIDRAM['Components']['ThisTarget']]['Version'] .
                substr($ThisData, $PlugHeadEnd);
            $Handle = fopen($CIDRAM['Vault'] . '../cidram.php', 'w');
            fwrite($Handle, $ThisData);
            fclose($Handle);
        }
    }
};

/**
 * Localises a number according to configuration specification.
 *
 * @param int $Number The number to localise.
 * @param int $Decimals Decimal places (optional).
 */
$CIDRAM['Number_L10N'] = function ($Number, $Decimals = 0) use (&$CIDRAM) {
    $Number = (real)$Number;
    $Sets = array(
        'NoSep-1' => ['.', '', 3, false, 0],
        'NoSep-2' => [',', '', 3, false, 0],
        'Latin-1' => ['.', ',', 3, false, 0],
        'Latin-2' => ['.', ' ', 3, false, 0],
        'Latin-3' => [',', '.', 3, false, 0],
        'Latin-4' => [',', ' ', 3, false, 0],
        'Latin-5' => ['·', ',', 3, false, 0],
        'China-1' => ['.', ',', 4, false, 0],
        'India-1' => ['.', ',', 2, false, -1],
        'India-2' => ['.', ',', 2, ['०', '१', '२', '३', '४', '५', '६', '७', '८', '९'], -1],
        'Bengali-1' => ['.', ',', 2, ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'], -1],
        'Arabic-1' => ['٫', '', 3, ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'], 0],
        'Arabic-2' => ['٫', '٬', 3, ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'], 0],
        'Thai-1' => ['.', ',', 3, ['๐', '๑', '๒', '๓', '๔', '๕', '๖', '๗', '๘', '๙'], 0]
    );
    $Set = empty($Sets[$CIDRAM['Config']['general']['numbers']]) ? 'Latin-1' : $Sets[$CIDRAM['Config']['general']['numbers']];
    $DecPos = strpos($Number, '.') ?: strlen($Number);
    if ($Decimals && $Set[0]) {
        $Fraction = substr($Number, $DecPos + 1, $Decimals);
        $Fraction .= str_repeat('0', $Decimals - strlen($Fraction));
    }
    for ($Formatted = '', $ThouPos = $Set[4], $Pos = 1; $Pos <= $DecPos; $Pos++) {
        if ($ThouPos >= $Set[2]) {
            $ThouPos = 1;
            $Formatted = $Set[1] . $Formatted;
        } else {
            $ThouPos++;
        }
        $NegPos = $DecPos - $Pos;
        $ThisChar = substr($Number, $NegPos, 1);
        $Formatted = empty($Set[3][$ThisChar]) ? $ThisChar . $Formatted : $Set[3][$ThisChar] . $Formatted;
    }
    if ($Decimals && $Set[0]) {
        $Formatted .= $Set[0];
        for ($FracLen = strlen($Fraction), $Pos = 0; $Pos < $FracLen; $Pos++) {
            $Formatted .= empty($Set[3][$Fraction[$Pos]]) ? $Fraction[$Pos] : $Set[3][$Fraction[$Pos]];
        }
    }
    return $Formatted;
};

/**
 * Generates JavaScript code for localising numbers according to configuration
 * specification.
 */
$CIDRAM['Number_L10N_JS'] = function () use (&$CIDRAM) {
    $Base =
        'function l10nn(l10nd){%4$s};function nft(r){var x=r.indexOf(\'.\')!=-1?' .
        '\'%1$s\'+r.replace(/^.*\./gi,\'\'):\'\',n=r.replace(/\..*$/gi,\'\').rep' .
        'lace(/[^0-9]/gi,\'\'),t=n.length;for(e=\'\',b=%5$d,i=1;i<=t;i++){b>%3$d' .
        '&&(b=1,e=\'%2$s\'+e);var e=l10nn(n.substring(t-i,t-(i-1)))+e;b++}var t=' .
        'x.length;for(y=\'\',b=1,i=1;i<=t;i++){var y=l10nn(x.substring(t-i,t-(i-' .
        '1)))+y}return e+y}';
    $Sets = array(
        'NoSep-1' => ['.', '', 3, 'return l10nd', 1],
        'NoSep-2' => [',', '', 3, 'return l10nd', 1],
        'Latin-1' => ['.', ',', 3, 'return l10nd', 1],
        'Latin-2' => ['.', ' ', 3, 'return l10nd', 1],
        'Latin-3' => [',', '.', 3, 'return l10nd', 1],
        'Latin-4' => [',', ' ', 3, 'return l10nd', 1],
        'Latin-5' => ['·', ',', 3, 'return l10nd', 1],
        'China-1' => ['.', ',', 4, 'return l10nd', 1],
        'India-1' => ['.', ',', 2, 'return l10nd', 0],
        'India-2' => ['.', ',', 2, 'var nls=[\'०\',\'१\',\'२\',\'३\',\'४\',\'५\',\'६\',\'७\',\'८\',\'९\'];return nls[l10nd]||l10nd', 0],
        'Bengali-1' => ['.', ',', 2, 'var nls=[\'০\',\'১\',\'২\',\'৩\',\'৪\',\'৫\',\'৬\',\'৭\',\'৮\',\'৯\'];return nls[l10nd]||l10nd', 0],
        'Arabic-1' => ['٫', '', 3, 'var nls=[\'٠\',\'١\',\'٢\',\'٣\',\'٤\',\'٥\',\'٦\',\'٧\',\'٨\',\'٩\'];return nls[l10nd]||l10nd', 1],
        'Arabic-2' => ['٫', '٬', 3, 'var nls=[\'٠\',\'١\',\'٢\',\'٣\',\'٤\',\'٥\',\'٦\',\'٧\',\'٨\',\'٩\'];return nls[l10nd]||l10nd', 1],
        'Thai-1' => ['.', ',', 3, 'var nls=[\'๐\',\'๑\',\'๒\',\'๓\',\'๔\',\'๕\',\'๖\',\'๗\',\'๘\',\'๙\'];return nls[l10nd]||l10nd', 1],
    );
    if (!empty($CIDRAM['Config']['general']['numbers']) && isset($Sets[$CIDRAM['Config']['general']['numbers']])) {
        $Set = $Sets[$CIDRAM['Config']['general']['numbers']];
        return sprintf($Base, $Set[0], $Set[1], $Set[2], $Set[3], $Set[4]);
    }
    return sprintf($Base, $Sets['Latin-1'][0], $Sets['Latin-1'][1], $Sets['Latin-1'][2], $Sets['Latin-1'][3], $Sets['Latin-1'][4]);
};

/**
 * Swaps for the values of two variables entered as parameters.
 * Note: For PHP >= 7, we can use "[$First, $Second] = [$Second, $First];" and
 * do away with this closure entirely, but we'll need it for now, at least,
 * until we up the minimum package requirements.
 */
$CIDRAM['Swap'] = function(&$First, &$Second) {
    $Working = $First;
    $First = $Second;
    $Second = $Working;
};

/**
 * Switch control for front-end page filters.
 *
 * @param array $Switches Names of available switches.
 * @param string $Selector Switch selector variable.
 * @param bool $StateModified Determines whether the filter state has been modified.
 * @param string $Redirect Reconstructed path to redirect to when the state changes.
 * @param string $Options Recontructed filter controls.
 */
$CIDRAM['FilterSwitch'] = function($Switches, $Selector, &$StateModified, &$Redirect, &$Options) use (&$CIDRAM) {
    foreach ($Switches as $Switch) {
        $State = (!empty($Selector) && $Selector === $Switch);
        $CIDRAM['FE'][$Switch] = empty($CIDRAM['QueryVars'][$Switch]) ? false : (
            ($CIDRAM['QueryVars'][$Switch] === 'true' && !$State) ||
            ($CIDRAM['QueryVars'][$Switch] !== 'true' && $State)
        );
        if ($State) {
            $StateModified = true;
        }
        if ($CIDRAM['FE'][$Switch]) {
            $Redirect .= '&' . $Switch . '=true';
            $LangItem = 'switch-' . $Switch . '-set-false';
        } else {
            $Redirect .= '&' . $Switch . '=false';
            $LangItem = 'switch-' . $Switch . '-set-true';
        }
        $Label = isset($CIDRAM['lang'][$LangItem]) ? $CIDRAM['lang'][$LangItem] : $LangItem;
        $Options .= '<option value="' . $Switch . '">' . $Label . '</option>';
    }
};
