<?php
/**
 * This file is a part of the CIDRAM package, and can be downloaded for free
 * from {@link https://github.com/Maikuolan/CIDRAM/ GitHub}.
 *
 * CIDRAM COPYRIGHT 2016 and beyond by Caleb Mazalevskis (Maikuolan).
 *
 * License: GNU/GPLv2
 * @see LICENSE.txt
 *
 * This file: Italian language data (last modified: 2016.04.01).
 */

/** Prevents execution from outside of CIDRAM. */
if (!defined('CIDRAM')) {
    die('[CIDRAM] This should not be accessed directly.');
}

$CIDRAM['lang']['click_here'] = 'clicca qui';
$CIDRAM['lang']['denied'] = 'Accesso Negato!';
$CIDRAM['lang']['Error_WriteCache'] = 'Non può scrivere nella cache! Si prega di controllare le autorizzazioni di CHMOD!';
$CIDRAM['lang']['field_datetime'] = 'Data/Tempo: ';
$CIDRAM['lang']['field_id'] = 'ID: ';
$CIDRAM['lang']['field_ipaddr'] = 'Indirizzo IP: ';
$CIDRAM['lang']['field_query'] = 'Query: ';
$CIDRAM['lang']['field_referrer'] = 'Referente: ';
$CIDRAM['lang']['field_scriptversion'] = 'Versione dello script: ';
$CIDRAM['lang']['field_sigcount'] = 'Firme Conteggio: ';
$CIDRAM['lang']['field_sigref'] = 'Firme Riferimento: ';
$CIDRAM['lang']['field_whyreason'] = 'Perché Bloccato: ';
$CIDRAM['lang']['field_ua'] = 'User Agent: ';
$CIDRAM['lang']['field_rURI'] = 'URI Ricostruito: ';
$CIDRAM['lang']['generated_by'] = 'Generato da';
$CIDRAM['lang']['preamble'] = '-- Fine del preambolo. Aggiungere le vostre domande o commenti dopo questa linea. --';
$CIDRAM['lang']['ReasonMessage_BadIP'] = 'Accesso a questa pagina è stato negato perché si è tentato di accedere a questa pagina utilizzando un indirizzo IP non valido.';
$CIDRAM['lang']['ReasonMessage_Bogon'] = 'Accesso a questa pagina è stato negato perché il suo indirizzo IP viene riconosciuto come un indirizzo bogone, e il collegamento da bogoni a questo sito web non è consentito dal proprietario del sito web.';
$CIDRAM['lang']['ReasonMessage_Cloud'] = 'Accesso a questa pagina è stato negato perché il suo indirizzo IP viene riconosciuto come appartenente ad un servizio cloud, e il collegamento a questo sito web da servizi cloud non è consentito dal proprietario del sito web.';
$CIDRAM['lang']['ReasonMessage_Generic'] = 'Accesso a questa pagina è stato negato perché il suo indirizzo IP appartiene a una rete elencati in una lista nera utilizzato da questo sito web.';
$CIDRAM['lang']['ReasonMessage_Spam'] = 'Accesso a questa pagina è stato negato perché il suo indirizzo appartiene a una rete considerati ad alto rischio per lo spam.';
$CIDRAM['lang']['Short_BadIP'] = 'IP non valido!';
$CIDRAM['lang']['Short_Bogon'] = 'Bogon IP';
$CIDRAM['lang']['Short_Cloud'] = 'Servizio cloud';
$CIDRAM['lang']['Short_Generic'] = 'Generico';
$CIDRAM['lang']['Short_Spam'] = 'Rischio per lo spam';
$CIDRAM['lang']['Support_Email'] = 'Se credi che questo è in errore, o per cercare assistenza, {ClickHereLink} per inviare una richiesta di assistenza via e-mail per il webmaster di questo sito (si prega di non modificare il preambolo o linea oggetto dell\'e-mail).';

$CIDRAM['lang']['CLI_H'] = "
 CIDRAM CLI modalità aiuto.

 Uso:
 /cartella/a/php/php.exe /cartella/a/cidram/loader.php -Flag (Ingresso)

 Flags: -h  Visualizzare queste informazioni di aiuto.
        -c  Verificare se un indirizzo IP è bloccato dalle firme di CIDRAM.
        -g  Generare CIDRs da un indirizzo IP.

 Ingresso: Può essere qualsiasi indirizzo IPv4 o IPv6 valido.

 Esempi:
        -c  192.168.0.0/16
        -c  127.0.0.1/32
        -c  2001:db8::/32
        -c  2002::1/128

";

$CIDRAM['lang']['CLI_Bad_IP'] = ' L\'indirizzo IP specificato, "{IP}", non è un indirizzo IPv4 o IPv6 valido!';
$CIDRAM['lang']['CLI_IP_Blocked'] = ' L\'indirizzo IP specificato, "{IP}", è bloccato da uno o più delle firme di CIDRAM.';
$CIDRAM['lang']['CLI_IP_Not_Blocked'] = ' L\'indirizzo IP specificato, "{IP}", *NON* è bloccato da uno o più delle firme di CIDRAM.';