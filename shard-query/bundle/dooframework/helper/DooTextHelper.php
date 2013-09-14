<?php
/**
 * DooTextHelper class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009 Leng Sheng Hong
 * @license http://www.doophp.com/license
 */

/**
 * A helper class that helps to manipulate text.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @version $Id: DooTextHelper.php 1000 2009-08-4 11:17:22
 * @package doo.helper
 * @since 1.1
 */
class DooTextHelper {

    /**
     * Generates a random string.
     * @param int $length Length of the generated string
     * @return string
     */
    public static function randomName( $length = 6 ){
        $allchar = 'abcdefghijklmnopqrstuvwxyz01234567890';
        $str = "" ;
        mt_srand (( double) microtime() * 1000000 );
        for( $i=0; $i<$length; $i++ )
            $str .= substr( $allchar, mt_rand (0,36), 1 ) ;
        return date("YmdHis").rand(1000,9999).$str ;
    }

    /**
     * Removing repeated words from text.
     * @param string $str Original text to be processed.
     * @return string
     */
    public static function removeRepeatWords($str){
        return preg_replace("/s(w+s)1/i", "$1", $str);
    }


    /**
     * Removing repeated punctuation from text.
     * @param string $str Original text to be processed.
     * @return string
     */
    public static function removeRepeatPunc($str){
        return preg_replace("/.+/i", ".", $text);
    }


    /**
     * Convert all URLs in the text into hyperlinks.
     *
     * All URLs are converted. http://domain.com, www.domain.com, email@address.com
     * Passed in a false to disable converting Email to link.
     *
     * @param string $str
     * @param string $classname CSS class name of the link tag
     * @param string $target Target: _self, _blank, _parent, etc.
     * @param bool $convertEmail Convert email address into links. Default true.
     * @param string $emailHide Change this if you want to hide the email. eg. leng[AT]doophp.com, value = '[AT]'
     * @return string
     */
    public static function convertUrl($str, $classname='', $target='', $convertEmail=true, $emailHide='@'){
        if($classname!='')
            $classname = "class=\"$classname\"";

        if($target!='')
            $target = "target=\"$target\"";

        $str = preg_replace("#([ ]|^|])([a-z]+?)://([a-z0-9@:\-]+)\.([a-z0-9@:\-.\~]+)((?:/[^ ]*)?[^][\".,?!;: ])#i", "\\1<a href=\"\\2://\\3.\\4\\5\" $target $classname>\\2://\\3.\\4\\5</a>", $str);
        $str = preg_replace("#([ ]|^|])www\.([a-z0-9\-]+)\.([a-z0-9:\-.\~]+)((?:/[^ ]*)?[^][\".,?!;: ])#i", "\\1<a href=\"http://www.\\2.\\3\\4\" $target $classname>www.\\2.\\3\\4</a>", $str);

        if($convertEmail)
            if($emailHide=='@')
                $str = preg_replace("#([ ]|^|])([a-z0-9\-_.]+?)@([\w\-]+\.([\w\-\.]+\.)?[\w]+[^][\".,?!;: ])#i", "\\1<a href=\"mailto:\\2@\\3\" $target $classname>\\2@\\3</a>", $str);
            else
                $str = preg_replace("#([ ]|^|])([a-z0-9\-_.]+?)@([\w\-]+\.([\w\-\.]+\.)?[\w]+[^][\".,?!;: ])#i", "\\1\\2$emailHide\\3", $str);

        return $str;
    }

    /**
     * Count words.
     * @param string $str Input String
     * @return int Number of words
     */
    public static function countWord($str){
        return str_word_count($str);
    }

    /**
     * Escape/encode a string
     *
     * @param string $str The input string
     * @param string $escapeType Escape type: html, htmlall, url
     * @param string $charSet Character set, default ISO-8859-1
     * @return string
     */
    public static function escape($str, $escapeType='html', $charSet='ISO-8859-1'){
        switch ($escapeType){
            case 'url':
                return rawurlencode($str);

            case 'urlpathinfo':
                return str_replace('%2F','/',rawurlencode($str));

            case 'quotes':
                return preg_replace("%(?<!\\\\)'%", "\\'", $str);

            case 'hex':
                $rs = '';
                for($i=0; $i < strlen($str); $i++) {
                    $rs .= '%' . bin2hex($str[$i]);
                }
                return $rs;

            case 'hexentity':
                $rs = '';
                for($i=0; $i < strlen($str); $i++) {
                        $rs .= '&#x' . bin2hex($str[$i]) . ';';
                }
                return $rs;

            case 'decentity':
                $rs = '';
                for($i=0; $i < strlen($str); $i++) {
                    $rs .= '&#' . ord($str[$i]) . ';';
                }
                return $rs;

            case 'html':
               return htmlspecialchars($str, ENT_COMPAT, $charSet);

            case 'htmlall':
                return htmlentities($str, ENT_COMPAT, $charSet);

            case 'js':
                return strtr($str, array('\\'=>'\\\\',"'"=>"\\'",'"'=>'\\"',"\r"=>'\\r',"\n"=>'\\n','</'=>'<\/'));

            case 'mail':
                return str_replace(array('@', '.'),array(' [AT] ', ' [DOT] '), $str);

            case 'nonstd':
                $rs = '';
                for($i = 0, $length = strlen($str); $i < $length; $i++) {
                    $ord = ord(substr($str, $i, 1));
                    $rs .= $ord >= 126 ? '&#' . $ord . ';' : substr($str, $i, 1);
                }
                return $rs;
            default:
                return $str;
        }
    }

    /**
     * Filter/censor word(s) from a string input
     *
     * <p>A list of disallowed words will be replaced to '*' or the replacement value
     * from the input string.</p>
     *
     * @param string $str Input string to be processed
     * @param array $censorWords List of words to be censored
     * @param string $replacement Optional replacement string for the censored word
     * @param bool $word True to be an exact word, False to censor within word 'Youprofanity'
     * @return string
     */
    public static function filter($str, $censorWords, $replacement='*', $word=true){
	if(!$word)
	    return str_ireplace($censorWords, $replacement, $str);

        foreach($censorWords as $c)
            $str = preg_replace("/\b(" . str_replace('\*', '\w*?', preg_quote($c)) . ")\b/i", $replacement, $str);

        return $str;
    }

    /**
     * Highlight PHP code
     *
     * @param string $str String of the PHP script
     * @return string
     */
    public static function highlightPHP($str){
        $str = str_replace(array('&lt;', '&gt;'), array('<', '>'), $str);
        $str = str_replace(array('&lt;?php', '?&gt;',  '\\'), array('phptagopen', 'phptagclose', 'backslashtmp'), $str);
        $str = '<?php //tempstart' . "\n" . $str . '//tempend ?>';
        $str = highlight_string($str, true);
        if(abs(phpversion()) < 5){
            $str = str_replace(array('<font ', '</font>'), array('<span ', '</span>'), $str);
            $str = preg_replace('#color="(.*?)"#', 'style="color: \\1"', $str);
        }
        $str = preg_replace("#\<code\>.+?//tempstart\<br />\</span\>#is", "<code>\n", $str);
        //$str = preg_replace("#\<code\>.+?//tempstart\<br />#is", "<code>\n", $str);
        $str = preg_replace("#//tempend.+#is", "</span>\n</code>", $str);
        $str = str_replace(array('phptagopen', 'phptagclose', 'backslashtmp'), array('&lt;?php', '?&gt;', '\\'), $str);
        return $str;
    }

    /**
     * Highlight word(s) within a string
     *
     * @param string $str String input
     * @param string $phrase The phrase to be highlighted
     * @param string $tagOpen HTML tag added right before the word
     * @param string $tagClose HTML tag added right after the word
     * @return string
     */
    public static function highlightWord($str, $phrase, $tagOpen='<span style="font-weight:bold;background-color:#ffff00">', $tagClose='</span>'){
        return preg_replace('/(' . preg_quote($phrase) . ')/i', $tagOpen . "\\1" . $tagClose, $str);
    }

    /**
     * Limit the string to a certain amount of words.
     *
     * @param string $str String input
     * @param int $limit Number of words to limit
     * @param string $ending End characters. Default '...'
     * @return string
     */
    public static function limitWord($str, $limit, $ending='...'){
        if(strlen($str) < $limit)
            return $str;

        $words = explode(' ', preg_replace("/\s+/", ' ', preg_replace("/(\r\n|\r|\n)/", " ", $str)));

        if(sizeof($words)<=$limit)
            return $str;

        $str = '';
        for($i=0; $i<$limit; $i++){
            $str .= $words[$i].' ';
        }
        return $str . $ending;
    }

    /**
     * Limit the string to a certain amount of characters.
     *
     * @param string $str String input
     * @param int $limit Number of characters to limit
     * @param string $ending End characters. Default '...'
     * @param string $encoding The character encoding. eg. utf8
     * @return string
     */
    public static function limitChar($str, $limit, $ending='...', $encoding=null){
        if($encoding==null){
            if(strlen($str) <= $limit)
                return $str;

            return substr($str, 0, $limit) . $ending;
        }else{
            if(mb_strlen($str, $encoding) <= $limit)
                return $str;

            return mb_substr($str, 0, $limit, $encoding) . $ending;
        }
    }

    /**
     * Replace all repeated spaces, newlines and tabs.
     *
     * @param string $str String input
     * @param string $replace Replacement value (optional)
     * @return string
     */
    public static function strip($str, $replace=' '){
        return preg_replace('/\s+/', $replace, $str);
    }

     /**
      * Word wraping of string
      *
      * Anything within [nowrap][/nowrap] in the string won't be wrapped.
      * 
      * @param string $str String input
      * @param int $charLimit Character limit per line
      * @param string $breakline Break line HTML replacement
      * @return string
      */
    public static function wordwrap($str, $charLimit, $breakline='<br/>'){
        if(!is_numeric($charLimit))
                $charLimit = 76;

        $str = preg_replace("| +|", " ", $str);
        $str = preg_replace("/\r\n|\r/", "\n", $str);

        $nowrap = array();
        if(preg_match_all("|(\[nowrap\].+?\[/nowrap\])|s", $str, $matches)){
            $count = count($matches['0']);
            for($i = 0; $i < $count; $i++){
                $nowrap[] = $matches['1'][$i];
                $str = str_replace($matches['1'][$i], "[[nowrapped".$i."]]", $str);
            }
        }

        $str = wordwrap($str, $charLimit, $breakline, false);

        $output = '';
        foreach(explode($breakline, $str) as $line) {
            if(strlen($line) <= $charLimit){
                $output .= $line . $breakline;
                continue;
            }

            $temp = '';
            while((strlen($line)) > $charLimit){
                if(preg_match("!\[url.+\]|://|wwww.!", $line))
                    break;

                $temp .= substr($line, 0, $charLimit-1);
                $line = substr($line, $charLimit-1);
            }
            $output .= ($temp != '' ? $temp.$line : $line) . $breakline;
        }

        if(count($nowrap) > 0){
            foreach($nowrap as $key => $val){
                $output = str_replace("[[nowrapped".$key."]]", $val, $output);
            }
        }

        $output = str_replace(array('[nowrap]', '[/nowrap]'), '', $output);
        return $output;
    }
}
