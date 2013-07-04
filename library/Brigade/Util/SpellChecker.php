<?php

class Brigade_Util_SpellChecker {

    public function checkSpelling($phrase) {
        header("Content-Type: text/xml; charset=utf-8");
        $url="https://www.google.com/tbproxy/spell";
        $text = urldecode($phrase);

        $body = '<?xml version="1.0" encoding="utf-8" ?>';
        $body .= '<spellrequest textalreadyclipped="0" ignoredups="1" ignoredigits="1" ignoreallcaps="1">';
        $body .= '<text>"' . $text . '"</text>';
        $body .= '</spellrequest>';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $contents = curl_exec($ch);
        curl_close($ch);

        header("Content-Type: text/html; charset=utf-8");
        $contents = substr($contents, strripos($contents,'">')+2, strlen($contents)-strripos('suggestedlang="en">',$contents));
        return explode('	', str_replace(array('<?xml version="1.0" encoding="UTF-8"?>', '<spellresult error="0" clipped="0" charschecked="7" suggestedlang="en">','<c o="1" l="5" s="0">','</c>','</spellresult>'), array('','','','',''), $contents));
    }

}

?>