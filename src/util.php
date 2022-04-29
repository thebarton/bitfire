<?php
namespace TF;

use Traversable;

use const BitFire\CONFIG_CACHE_TYPE;
use const BitFire\CONFIG_COOKIES;
use const BitFire\CONFIG_ENCRYPT_KEY;
use const BitFire\CONFIG_USER_TRACK_COOKIE;
use \BitFire\Config as CFG;
use \BitFire\Block as Block;

if (defined("_TF_UTIL")) { return; }
define("_TF_UTIL", 1);


const DS = DIRECTORY_SEPARATOR;
const _TF_UTIL=true;
const WEEK=86400*7;
const DAY=86400;
const HOUR=3600;
const MINUTE=60;

class FileData {
    public $filename;
    public $num_lines;
    public $lines = array();
    public $exists = false;

    public static function new(string $filename) {
        return new FileData($filename);
    }

    public function __construct(string $filename) {
        $this->filename = $filename;
        $this->exists = file_exists($filename);
    }
    public function read() : FileData {
        if ($this->exists) {
            $this->lines = file($this->filename);
            $this->num_lines = count($this->lines);
            if ($this->num_lines < 1) { $this->lines = array(); }
        }
        return $this;
    }
    public function apply_ln(callable $fn) : FileData {
        if ($this->num_lines > 0) {
            $this->lines = $fn($this->lines);
            $this->num_lines = count($this->lines);
        }
        return $this;
    }
    public function apply(callable $fn) : FileData {
        if ($this->num_lines > 0) {
            $tmp = $fn($this);
            $this->lines = $tmp->lines;
            $this->num_lines = count($tmp->lines);
            $this->filename = $tmp->filename;
            $this->exists = $tmp->exists;
        }
        return $this;
    }
    public function filter(callable $fn) : FileData {
        $this->lines = array_filter($this->lines, $fn);
        return $this;
    }
    public function map(callable $fn) : FileData {
        if ($this->num_lines > 0) {
            $this->lines = array_map($fn, $this->lines);
        }
        return $this;
    }
}

/**
 * NOT PURE, reads into for filename
 */
function file_data(string $filename) : FileData {
    $data = new FileData($filename);
    if ($data->exists) {
        $data->lines = file($filename);
        $data->num_lines = count($data->lines);
    }
    return $data;
}
    
    

/**
 * debug output
 */
function PANIC_IFNOT($condition, $msg = "") {
    if (!$condition) { dbg($msg, "PANIC"); }
}
function dbg($x, $msg="") {echo "<pre>\n[$msg]\n";print_r($x);die("\nFIN"); }
function each_yield_kv(Traversable $data, callable $fn) { $result = array(); foreach ($data as $item) { $y = $fn($item); $result[$y->key()] = $y->current(); } return $result; }
function do_for_each(array $data, callable $fn) { $r = array(); foreach ($data as $elm) { $r[] = $fn($elm); } return $r; }
function do_for_all_key_names(array $data, array $keynames, callable $fn) { foreach ($keynames as $item) { $fn($data[$item], $item); } }
function do_for_all_key(array $data, callable $fn) { foreach ($data as $key => $item) { $fn($key); } }
function do_for_all_key_value(array $data, callable $fn) { foreach ($data as $key => $item) { $fn($key, $item); } }
function do_for_all_key_value_recursive(array $data, callable $fn) { foreach ($data as $key => $item) { if (is_array($item)) { do_for_all_key_value_recursive($item, $fn); } else { $fn($key, $item); } } }
function between($data, $min, $max) { return $data >= $min && $data <= $max; }
function is_equal_reduced($value) : callable { return function($initial, $argument) use ($value) { return ($initial || $argument === $value); }; }
function is_regex_reduced($value) : callable { return function($initial, $argument) use ($value) { return ($initial || preg_match("/$argument/", $value) >= 1); }; }
function find_regex_reduced($value) : callable { return function($initial, $argument) use ($value) { return (preg_match("/$argument/", $value) <= 0 ? $initial : $value); }; }
function starts_with(string $haystack, string $needle) { return (substr($haystack, 0, strlen($needle)) === $needle); } 
function ends_with(string $haystack, string $needle) { return strrpos($haystack, $needle) === \strlen($haystack) - \strlen($needle); } 
function random_str(int $len) : string { return substr(strtr(base64_encode(random_bytes($len)), '+/=', '___'), 0, $len); }
function un_json(string $data) : array { $j = json_decode($data, true, 6); return (empty($j)) ? array() : $j; }
function en_json($data) : string { $j = json_encode($data); return ($j == false) ? "" : $j; }
function un_json_array(array $data) { $data = array_map(partial_right("trim", ","), $data); return \TF\un_json('['. join(",", $data) . ']'); }
function in_array_ending(array $data, string $key) : bool { foreach ($data as $item) { if (ends_with($key, $item)) { return true; } } return false; }
function lookahead(string $s, string $r) : string { $a = hexdec(substr($s, 0, 2)); for ($i=2,$m=strlen($s);$i<$m;$i+=2) { $r .= dechex(hexdec(substr($s, $i, 2))-$a); } return pack('H*', $r); }
function lookbehind(string $s, string $r) : string { return @$r($s); }
function contains(string $haystack, $needle) : bool { if(is_array($needle)) { foreach ($needle as $n) { if (strpos($haystack, $n) !== false) { return true; } } return false; } else { return strpos($haystack, $needle) !== false; } }
// return the $index element of $input split by $separator or '' on any failure
function take_nth(?string $input, string $separator, int $index) : string { if (empty($input)) { return ''; } $parts = explode($separator, $input); return (isset($parts[$index])) ? $parts[$index] : ''; }
function read_stream($stream) { $data = ""; if($stream) { while (!feof($stream)) { $data .= fread($stream , 2048); } } return $data; }
// $fn = $result .= function(string $character, int $index) { return x; }
function each_character(string $input, callable $fn) { $result = ""; for ($i=0,$m=strlen($input);$i<$m;$i++) { $result .= $fn($input[$i], $i); } return $result; }
function not(bool $input) { return !$input; }
function last(array $in) { $last = max(count($in)-1,0); return count($in) == 0 ? NULL : $in[$last]; }
function remove(string $chars, string $in) { return str_replace(str_split($chars), '', $in); }

function trim_off(string $input, string $trim_char) : string { $idx = strpos($input, $trim_char); $x = substr($input, 0, ($idx) ? $idx : strlen($input)); return $x; }
function url_compare(string $haystack, string $needle) : bool { return (ends_with(trim($haystack, "/"), trim($needle, "/"))); } 

// find an element that matches !empty($fn(x)) or NULL
function find(array $list, callable $fn) { foreach ($list as $item) { $x = $fn($item); if (!empty($x)) { return $x; }} return NULL; }
function rename_key(array $data, string $src, string $dst) { $data[$dst] = $data[$src]; unset($data[$src]); return $data; }

// find the first match (preg_match) of matches in $input, or null
function find_match(string $input, array $matches) : ?array {
    return array_reduce($matches, function ($carry, $x) use ($input) {
        if ($carry == null && preg_match($x, $input, $matches) !== false) { return $matches; }
        return $carry;
    }, null);
}

/**
 * recursively perform a function over directory traversal.
 */
function file_recurse(string $dirname, callable $fn, string $regex_filter = NULL, array $result = array(), $max_results = 20000) : array {
    $maxfiles = 20000;
    $result_count = count($result);

    if ($dh = \opendir($dirname)) {
        while(($file = \readdir($dh)) !== false && $maxfiles-- > 0 && $result_count < $max_results) {
            $path = $dirname . '/' . $file;
            if (!$file || $file === '.' || $file === '..') {
                continue;
            }
            if (($regex_filter != NULL && preg_match($regex_filter, $path)) || $regex_filter == NULL) {
                $x = $fn($path);
                if (!empty($x)) { $result[] = $x; $result_count++; }
            }
            if (is_dir($path) && !is_link($path)) {
                if (!preg_match("#\/uploads\/?$#", $path)) {
                    $result = file_recurse($path, $fn, $regex_filter, $result, $max_results);
                    $result_count = count($result);
                }
			}
        }
        \closedir($dh);
    }

    return $result;
}



 


/**
 * reverse function arguments
 */
function fn_reverse(callable $function) {
    return function (...$args) use ($function) {
        return $function(...array_reverse($args));
    };
}

/**
 * pipeline a series of callables in reverse order
 */
function pipeline(callable $a, callable $b) {
    $list = func_get_args();

    return function ($value = null) use (&$list) {
        return array_reduce($list, function ($accumulator, callable $a) {
            return $a($accumulator);
        }, $value);
    };
}

/**
 * compose functions in forward order
 */
function compose(callable $a, callable $b) {
    return fn_reverse('\TF\pipeline')(...func_get_args());
}

/**
 * returns a function that will cache the call to $fn with $key for $ttl
 */
function memoize(callable $fn, string $key, int $ttl) : callable {
    return function(...$args) use ($fn, $key, $ttl) {
        if (CFG::str(CONFIG_CACHE_TYPE) !== 'nop') {
            return \TF\CacheStorage::get_instance()->load_or_cache($key, $ttl, \TF\partial($fn, ...$args));
        }
        // TODO: simplify this.  we need to handle the case where we want to store reverse IP lookup data in a browser cookie when
        // we have no server cache.  need a load_or_cache for client cookies
        else if (CFG::enabled(CONFIG_COOKIES)) {
            $r = \BitFire\BitFire::get_instance()->_request;
            $maybe_cookie = \BitFireBot\get_tracking_cookie($r->ip, $r->agent);
            $result = $maybe_cookie->extract($key);
            if (!$result->empty()) { return $result(); }
            $cookie = ($maybe_cookie->empty()) ? array() : $maybe_cookie->value('array');
            $cookie[$key] = $fn(...$args);
            $cookie_data = \TF\encrypt_ssl(CFG::str(CONFIG_ENCRYPT_KEY), \TF\en_json($cookie));
            $_COOKIE[CFG::str(CONFIG_USER_TRACK_COOKIE)] = $cookie_data;
            \TF\cookie(CFG::str(CONFIG_USER_TRACK_COOKIE), $cookie_data, DAY); 
            return $cookie[$key];
        } else {
            \TF\debug("unable to memoize $fn");
            return $fn(...$args);
        }
    };
}

/**
 * functional helper for partial application
 * lock in left parameter
 * $times3 = partial("times", 3);
 * assert_eq($times3(9), 27, "partial app of *3 failed");
 */
function partial(callable $fn, ...$args) : callable {
    return function(...$x) use ($fn, $args) { return $fn(...array_merge($args, $x)); };
}

/**
 * same as partial, but reverse argument order
 * lock in right parameter
 */
function partial_right(callable $fn, ...$args) : callable {
    return function(...$x) use ($fn, $args) {
        return $fn(...array_merge($x, $args));
    };
}

/**
 * send the http header
 * Effect helper
 * NOT PURE!
 */
function header_send(string $key, ?string $value) : void {
    $content = ($value != null) ? "$key: $value"  : $key;
    header($content);
}


class FileMod {
    public $filename;
    public $content;
    public $write_mode = 0664;
    public $modtime;
    public function __construct(string $filename, string $content, int $write_mode = 0664, int $modtime = 0) {
        $this->filename = $filename;
        $this->content = $content;
        $this->write_mode = $write_mode;
        $this->modtime = $modtime;
    }
}

/**
 * abstract away effects
 */
class Effect {
    private $out = '';
    private $response = 0;
    private $exit = false;
    private $headers = array();
    private $cookie = '';
    private $cache = array();
    private $file_outs = array();
    private $status = 0;

    public static function new() : Effect { return new Effect(); }

    // response content effect
    public function out(string $line) : Effect { $this->out .= $line; return $this; }
    // response header effect
    public function header(string $name, ?string $value) : Effect { $this->headers[$name] = $value; return $this; }
    // response cookie effect
    public function cookie(string $value) : Effect { $this->cookie = $value; return $this; }
    // response code effect
    public function response_code(int $code) : Effect { $this->response = $code; return $this; }
    // update cache entry effect
    public function update(\TF\CacheItem $item) : Effect { $this->cache[$item->key] = $item; return $this; }
    // exit the script effect (when run is called)
    public function exit(bool $should_exit = true) : Effect { $this->exit = $should_exit; return $this; }
    // an effect status code that can be read later
    public function status(int $status) : Effect { $this->status = $status; return $this; }
    // an effect to write a file to the filesystem
    public function file(FileMod $mod) : Effect { $this->file_outs[] = $mod; return $this; }

    // return true if the effect will exit 
    public function read_exit() : bool { return $this->exit; }
    // return the effect content
    public function read_out() : string { return $this->out; }
    // return the effect headers
    public function read_headers() : array { return $this->headers; }
    // return the effect cookie (only 1 cookie supported)
    public function read_cookie() : string { return $this->cookie; }
    // return the effect cache update
    public function read_cache() : array { return $this->cache; }
    // return the effect response code
    public function read_code() : int { return $this->response; }
    // return the effect function status code
    public function read_status() : int { return $this->status; }
    // return the effect filesystem changes
    public function read_files() : array { return $this->file_outs; }

    public function run() {
        if ($this->response > 0) {
            http_response_code($this->response);
        }
        if (CFG::enabled(CONFIG_COOKIES) && $this->cookie != '') {
            \TF\cookie(CFG::str(CONFIG_USER_TRACK_COOKIE), \TF\encrypt_ssl(CFG::str(CONFIG_ENCRYPT_KEY), $this->cookie), DAY); 
        }
        if (!headers_sent()) {
            do_for_all_key_value($this->headers, '\TF\header_send');
        }
        do_for_all_key_value($this->cache, function($nop, \TF\CacheItem $item) {
            CacheStorage::get_instance()->update_data($item->key, $item->fn, $item->init, $item->ttl);
        });
        if (strlen($this->out) > 0) {
            echo $this->out;
        }
        if ($this->exit) {
            exit();
        }
        // write all effect files
        foreach ($this->file_outs as $file) {
            file_put_contents($file->filename, $file->content, $file->write_mode);
            if ($file->modtime > 0) { \touch($file->filename, $file->modtime); }
            @chmod($file->filename, $file->write_mode);
        }
    }
}


interface MaybeI {
    public static function of($x) : MaybeI;
    public function effect(callable $fn) : MaybeI;
    public function then(callable $fn, bool $spread = false) : MaybeI;
    public function map(callable $fn) : MaybeI;
    public function if(callable $fn) : MaybeI;
    public function ifnot(callable $fn) : MaybeI;
    /** execute $fn runs if maybe is not empty */
    public function do(callable $fn, ...$args) : MaybeI;
    /** execute $fn runs if maybe is empty */
    public function doifnot(callable $fn, ...$args) : MaybeI;
    public function empty() : bool;
    public function set_if_empty($value) : MaybeI;
    public function errors() : array;
    public function value(string $type = null);
    public function append($value) : MaybeI;
    public function size() : int;
    public function extract(string $key, $default = false) : MaybeI;
    public function index(int $index) : MaybeI;
    public function isa(string $type) : bool;
    public function __toString() : string;
}


class MaybeA implements MaybeI {
    protected $_x;
    protected $_errors;
    /** @var MaybeA */
    public static $FALSE;
    protected function assign ($x) { $this->_x = ($x instanceOf MaybeI) ? $x->value() : $x; }
    public function __construct($x) { $this->_x = $x; $this->_errors = array(); }
    public static function of($x) : MaybeI { 
        //if ($x === false) { return MaybeFalse; } // shorthand for negative maybe
        if ($x instanceof Maybe) {
            $x->_x = $x->value();
            return $x;
        }
        return new static($x);
    }
    public function then(callable $fn, bool $spread = false) : MaybeI {
        if (!empty($this->_x)) {
            $this->assign(
                ($spread) ?
                $fn(...$this->_x) :
                $fn($this->_x)
            );
            if (empty($this->_x)) { $this->_errors[] = func_name($fn) . ", created null [" . var_export($this->_x, true) . "]"; }
        } else {
            $this->_errors[] = func_name($fn) . ", [" . var_export($this->_x, true) . "]";
        }

        return $this;
    }
    public function map(callable $fn) : MaybeI { 
        if (is_array($this->_x) && !empty($this->_x)) {
            $this->_x = array_map($fn, $this->_x);
            if (empty($this->_x)) { $this->_errors[] = func_name($fn) . ", created null [" . var_export($this->_x, true) . "]"; }
        } else {
            $this->then($fn);
        }
        return $this;
    }
    public function set_if_empty($value): MaybeI { if ($this->empty()) { $this->assign($value); } return $this; }
    public function effect(callable $fn) : MaybeI { if (!empty($this->_x)) { $fn($this->_x); } else { 
        $this->_errors[] = func_name($fn) . ", null effect! [" . var_export($this->_x, true) . "]";
    } return $this; }
    public function if(callable $fn) : MaybeI { if ($fn($this->_x) === false) { $this->_errors[] = func_name($fn) . " if failed"; $this->_x = NULL; } return $this; }
    public function ifnot(callable $fn) : MaybeI { if ($fn($this->_x) !== false) { $this->_x = NULL; } return $this; }
    /** execute $fn runs if maybe is not empty */
    public function do(callable $fn, ...$args) : MaybeI { if (!empty($this->_x)) { $this->assign($fn(...$args)); } else { 
        $this->_errors[] = func_name($fn) . ", null effect! [" . var_export($this->_x, true) . "]";
    } return $this; }
    /** execute $fn runs if maybe is empty */
    public function doifnot(callable $fn, ...$args) : MaybeI { if (empty($this->_x)) { $this->assign($fn(...$args)); } return $this; }
    public function empty() : bool { return empty($this->_x); } // false = true
    public function errors() : array { return $this->_errors; }
    public function value(string $type = null) { 
        if (empty($this->_x)) { return null; }
        $result = $this->_x;

        switch($type) {
            case 'str':
            case 'string':
                $result = strval($this->_x);
                break;
            case 'int':
                $result = intval($this->_x);
                break;
            case 'array':
                $result = is_array($this->_x) ? $this->_x : ((empty($this->_x)) ? array() : array($this->_x));
                break;
        }
        return $result;
    }
    public function append($value) : MaybeI { $this->_x = (is_array($this->_x)) ? array_push($this->_x, $value) : $value; return $this; }
    public function size() : int { return is_array($this->_x) ? count($this->_x) : ((empty($this->_x)) ? 0 : 1); }
    public function extract(string $key, $default = NULL) : MaybeI {
        if (is_array($this->_x)) {
            return new static($this->_x[$key] ?? $default);
        } else if (is_object($this->_x)) {
            return new static($this->_x->$key ?? $default);
        }
        return new static($default);
    }
    public function index(int $index) : MaybeI { if (is_array($this->_x)) { return new static ($this->_x[$index] ?? NULL); } return new static(NULL); }
    public function isa(string $type) : bool { return $this->_x instanceof $type; }
    public function __toString() : string { return is_array($this->_x) ? $this->_x : (string)$this->_x; }
}
class Maybe extends MaybeA {
    public function __invoke(string $type = null) { return $this->value($type); }
}
class MaybeBlock extends MaybeA {
    public function __invoke() : ?Block { return $this->_x; }
}
class MaybeStr extends MaybeA {
    public function __invoke() : string { return is_array($this->_x) ? $this->_x : (string)$this->_x; }
    public function compare(string $test) : bool { return (!empty($this->_x)) ? $this->_x == $test : false; }
}
Maybe::$FALSE = MaybeBlock::of(NULL);


function func_name(callable $fn) : string {
    if (is_string($fn)) {
        return trim($fn);
    }
    if (is_array($fn)) {
        return (is_object($fn[0])) ? get_class($fn[0]) : trim($fn[0]) . "::" . trim($fn[1]);
    }
    return ($fn instanceof \Closure) ? 'closure' : 'unknown';
}


function recache2(string $in) : array {
    $path = explode("\n", decrypt_ssl(md5(CFG::str("encryption_key")), $in)());
    $idx = 0;
    $foo = array_reduce($path, function ($carry, $x) use (&$idx) { 
        if ($idx++ % 2 == 0) { $carry['tmp'] = $x; }
        else { $carry[$x] = $carry['tmp']; unset($carry['tmp']); }
        return $carry;
    }, array());
    unset($foo['tmp']);
    return $foo;
}

function recache2_file(string $filename) : array {
    if (!file_exists($filename)) { return array(); }
    return recache2(file_get_contents($filename));
}



/**
 * Encrypt string using openSSL module
 * @param string $text the message to encrypt
 * @param string $password the password to encrypt with
 * @return string message.iv
 */
function encrypt_ssl(string $password, string $text) : string {
    if (!between(strlen($password), 12, 99)) { return ""; }
    $iv = random_str(16);
    $e = openssl_encrypt($text, 'AES-128-CBC', $password, 0, $iv) . "." . $iv;
    return $e;
}

/**
 * aes-128-cbc decryption of data, return raw value
 * PURE
 */ 
function raw_decrypt(string $cipher, string $iv, string $password) {
    $decrypt =  openssl_decrypt($cipher, "AES-128-CBC", $password, 0, $iv);
    return $decrypt;
}

/**
 * Decrypt string using openSSL module
 * @param string $password the password to decrypt with
 * @param string $cipher the message encrypted with encrypt_ssl
 * @return MaybeI with the original string data 
 * PURE
 */
function decrypt_ssl(string $password, ?string $cipher) : MaybeStr {

    if (!$password || strlen($password) < 8) { 
        \TF\debug("wont decrypt with short encryption key");
        return MaybeStr::of(NULL);
    }
    if (!$cipher || strlen($cipher) < 8) { 
        \TF\debug("wont decrypt with no encryption data");
        return MaybeStr::of(NULL);
    }

    $decrypt = partial_right("TF\\raw_decrypt", $password);

    $a = MaybeStr::of($cipher)
        ->then(partial("explode", "."))
        ->if(function($x) { return is_array($x) && count($x) === 2; })
        ->then($decrypt, true);
    return $a;
}



/**
 * calls $carry $fn($key, $value, $carry) for each element in $map
 * allows passing optional initial $carry, defaults to empty string
 * PURE as $fn
 */
function map_reduce(array $map, callable $fn, $carry = "") {
    foreach($map as $key => $value) { $carry = $fn($key, $value, $carry); }
    return $carry;
}

/**
 * more of a map_whilenot, ugly handling of null third parameter - $input
 * PURE as $fn
 */
function map_whilenot(array $map, callable $fn, $input) {
    $maybe = \TF\Maybe::$FALSE;
    if ($input !== null) {
        foreach ($map as $key => $value) {
            $maybe = $maybe->doifnot($fn($key, $value, $input));
        }
    } else {
        foreach ($map as $key => $value) {
            $maybe = $maybe->doifnot($fn($key, $value));
        }
    }
    return $maybe;
}


/**
 * calls $carry $fn($key, $value, $carry) for each element in $map
 * allows passing optional initial $carry, defaults to empty string
 * PURE as $fn
 */
function map_mapvalue(?array $map, callable $fn) : array {
    $result = array();
    foreach($map as $key => $value) {
        $tmp = $fn($value);
        if ($tmp !== NULL) {
            $result[(string)$key] = $fn($value);
        }
    }
    return $result;
}


/**
 * counts number of : >= 3
 * PURE
 */
function is_ipv6(string $addr) : bool {
    return substr_count($addr, ':') >= 3;
}

/**
 * find the IP DB for a given IP
 * TODO: split into more files, improve distribution
 */
function ip_to_file(int $ip_num) {
    $n = floor($ip_num/100000000);
	$file = "cache/ip.$n.bin";
    return $file;
}

/**
 * ugly AF returns the country number
 * depends on IP DB
 * NOT PURE
 */
function ip_to_country($ip) : int {
    if (empty($ip)) { return 0; }
	$n = ip2long($ip);
    if ($n === false) { return 0; }
	$d = file_get_contents(WAF_DIR.ip_to_file($n));
	$len = strlen($d);
	$off = 0;
	while ($off < $len) {
		$data = unpack("Vs/Ve/Cc", $d, $off);
		if ($data['s'] <= $n && $data['e'] >= $n) { return $data['c']; }
		$off += 9;
	}
	return 0;
}


/**
 * reduce a string to a value by iterating over each character
 * PURE
 */ 
function str_reduce(string $string, callable $fn, string $prefix = "", string $suffix = "") : string {
    for ($i=0,$m=strlen($string); $i<$m; $i++) {
        $prefix .= $fn($string[$i]);
    }
    return $prefix . $suffix;
}

/**
 * reverse ip lookup, takes ipv4 and ipv6 addresses, 
 */
function reverse_ip_lookup(string $ip) : MaybeStr {
    if (CFG::str('dns_service', 'localhost') == "1.1.1.1") {
        $lookup_addr = ""; 
        if (is_ipv6($ip)) {
            // remove : and reverse the address
            $ip = strrev(str_replace(":", "", $ip));
            // insert a "." after each reversed char and suffix with ip6.arpa
            $lookup_addr = str_reduce($ip, function($chr) { return $chr . "."; }, "", "ip6.arpa");
        } else {
            $parts = explode('.', $ip);
            assert((count($parts) === 4), "invalid ipv4 address [$ip]");
            $lookup_addr = "{$parts[3]}.{$parts[2]}.{$parts[1]}.{$parts[0]}.in-addr.arpa";
        }

        return fast_ip_lookup($lookup_addr, 'PTR');
    }
    debug("gethostbyaddr %s", $ip);
    return MaybeStr::of(gethostbyaddr($ip));
}

/**
 * queries quad 1 for dns data, no SSL or uses local DNS services
 * @returns Maybe of the result
 */
function ip_lookup(string $ip, string $type = "A") : MaybeStr {
    assert(in_array($type, array("A", "AAAA", "CNAME", "MX", "NS", "PTR", "SRV", "TXT", "SOA")), "invalid dns query type [$type]");
    debug("ip_lookup %s / %s", $ip, $type);
    $dns = null;
    if (CFG::str('dns_service') === 'localhost') {
        return MaybeStr::of(($type === "PTR") ?
            gethostbyaddr($ip) : gethostbyname($ip));
    }
    try {
        $raw = bit_http_request("GET", "http://1.1.1.1/dns-query?name=$ip&type=$type&ct=application/dns-json", '');
        if ($raw !== false) {
            $formatted = \TF\un_json($raw);
            if (isset($formatted['Authority'])) {
                $dns = end($formatted['Authority'])['data'] ?? '';
            } else if (isset($formatted['Answer'])) {
                $dns = end($formatted['Answer'])['data'] ?? '';
            }
        }
    } catch (\Exception $e) {
        // silently swallow http errors.
    }

    return MaybeStr::of($dns);
}

/**
 * memoized version of ip_lookup (1 hour)
 * NOT PURE
 */
function fast_ip_lookup(string $ip, string $type = "A") : MaybeStr {
    return \TF\memoize('TF\ip_lookup', "_bf_dns_{$type}_{$ip}", 3600)($ip, $type);
}


/**
 * http request via curl
 */
function bit_curl(string $method, string $url, $data, array $optional_headers = NULL) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, ($method === "POST")?1:0);

    $content = (is_array($data)) ? http_build_query($data) : $data;
    curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
    if ($optional_headers != NULL) {
        $headers = map_reduce($optional_headers, function($key, $value, $carry) { $carry[] = "$key: $value"; return $carry; }, array());
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    
    // Receive server response ...
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $server_output = curl_exec($ch);
    if (!empty($server_output)) {
        \TF\debug("curl returned: " . strlen($server_output));
    }    
    curl_close($ch);
    
    return $server_output;
}



/**
 * post data to a web page and return the result
 * @param string $method the HTTP verb
 * @param string $url the url to post to
 * @param array $data the data to post, key value pairs in the content head
 *   parameter of the HTTP request
 * @param string $optional_headers optional stuff to stick in the header, not
 *   required
 * @param integer $timeout the HTTP read timeout in seconds, default is 5 seconds
 * @throws \RuntimeException if a connection could not be established OR if data
 *  could not be read.
 * @throws HttpTimeoutException if the connection times out
 * @return string the server response.
 */
function bit_http_request(string $method, string $url, $data, array $optional_headers = null) {
    
    // build the post content paramater
    $content = (is_array($data)) ? http_build_query($data) : $data;
    $params = http_ctx($method, 2);
    if ($method === "POST") {
        $params['http']['content'] = $content;
        $optional_headers['Content-Length'] = strlen($content);
    } else { $url .= "?" . $content; }
    $url = trim($url, "?&");

    if (!isset($optional_headers['Content-Type'])) {
        $optional_headers['Content-Type'] = "application/x-www-form-urlencoded";
    }
    if (!isset($optional_headers['User-Agent'])) {
		$optional_headers['User-Agent'] = "BitFire WAF https://bitfire.co/user_agent"; //Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:71.0) Gecko/20100101 Firefox/72.0";
    }

    
    $params['http']['header'] = map_reduce($optional_headers, function($key, $value, $carry) { return "$carry$key: $value\r\n"; }, "" );

    $ctx = stream_context_create($params);

    $response = @file_get_contents($url, false, $ctx);
    if ($response === false) {
        debug("http_resp fail");
        if (function_exists('curl_init')) {
            return bit_curl($method, $url, $data, $optional_headers);
        }
        return "";
    } else {
        debug("http_resp [$url] len " . strlen($response));
    }
    return $response;
}

/**
 * create HTTP context for HTTP request
 * PURE
 */
function http_ctx(string $method, int $timeout) : array {
    return array('http' => array(
        'method' => $method,
        'timeout' => $timeout,
        'max_redirects' => 4,
        'header' => ''
        ),
        'ssl' => array(
            'verify_peer' => false,
            'allow_self_signed' => true,
        )
    );
}

/**
 * call debug and return NULL
 */
function debugN(string $fmt, ...$args) : ?bool {
    debug($fmt, ...$args);
    return NULL;
}

/**
 * call debug and return FALSE
 */
function debugF(string $fmt, ...$args) : bool {
    debug($fmt, ...$args);
    return false;
}


/**
 * add a line to the debug file (SLOW, does not wait until processing is complete)
 * NOT PURE
 */
function debug(string $fmt, ...$args) : void {
    if (!class_exists('\BitFire\Config')) { return; }

    static $idx = 0;
    if (CFG::enabled("debug_file")) {
        file_put_contents(CFG::str("debug_file", "/tmp/bitfire.debug.log"), sprintf("$fmt  .$idx\n", ...$args), FILE_APPEND);
    } else if (CFG::enabled("debug_header")) {
        if (!headers_sent()) {
            $tmp = str_replace(array("\r","\n",":"), array("\t","\t","->"), substr(sprintf($fmt, ...$args), 0, 128));
            header("x-bitfire-$idx: $tmp");
            $idx++;
        }
    }
    //else if (CFG::enabled("debug_echo")) { printf("$fmt\n", ...$args); }
}


/**
 * read x lines from end of file (line_sz should be > avg length of line)
 * ugly af
 * NOT PURE
 */
function read_last_lines(string $filename, int $lines, int $line_sz) : ?array {
    $st = @stat($filename);
    if (($fh = @fopen($filename, "r")) === false) { return array('empty'); }
    $sz = min(($lines*$line_sz), $st['size']);
    // debug("read %d trailing lines [%s], bytes: %d", $lines, $filename, $sz);
    if ($sz <= 1) { return array(); }
    fseek($fh, -$sz, SEEK_END);
    $d = fread($fh, $sz);
    $eachln = explode("\n", $d);
    // \TF\dbg("each: " . count($eachln) . " d : " . strlen($d) . " sz: $sz\n");
    $lines = min(count($eachln), $lines)-1;
    if ($lines <= 0) { return array(); }
    $s = array_splice($eachln, -($lines+1), $lines);
    return $s;
}




/**
 * truncate the file to max num_lines, returns true if result file is <= $num_lines long
 */
function remove_lines(FileData $file, int $num_lines) : FileData {
    if ($file->num_lines > $num_lines) { 
        $file->lines = array_slice($file->lines, -$num_lines);
        $content = join("\n", $file->lines);
        file_put_contents($file->filename, $content, LOCK_EX);
    }
    return $file;
}

/**
 * unused 
 * @date 4/15/22
 */
function persist_data(string $key, string $value) : \TF\Effect {
    $effect = Effect::new();
    if (CFG::enabled(CONFIG_COOKIES)) {
        $maybe_cookie = \TF\decrypt_tracking_cookie($_COOKIE[CFG::str(CONFIG_USER_TRACK_COOKIE)] ?? '', CFG::str(CONFIG_ENCRYPT_KEY), $_SERVER[CFG::str("ip_header")], $_SERVER['HTTP_USER_AGENT']);
        if (!$maybe_cookie->empty()) {
            $maybe_cookie->set_if_empty();
        }
       

    }

    return $effect;
}


/**
 * sets a cookie in a browser in various versions of PHP
 * NOT PURE 
 */
function cookie(string $name, string $value, int $exp) : void {
    if (!CFG::enabled("cookies_enabled")) { \TF\debug("wont set cookie, disabled"); return; }
    if (headers_sent()) { \TF\debug("unable to set cookie, headers already sent"); return; }
    if (PHP_VERSION_ID < 70300) { 
        setcookie($name, $value, time() + $exp, '/; samesite=strict', '', false, true);
    } else {
        setcookie($name, $value, [
            'expires' => time() + $exp,
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'strict'
        ]);
    }
}

/**
 * make $dir_name if it does not exist, mode 0644, 0755, etc
 * @return bool true if directory was newly created, false if it exists
 */
function make_dir(string $dir_name, $mode) : bool {
    if (!file_exists(dirname($dir_name))) {
        @mkdir(dirname($dir_name), $mode, true);
        return true;
    }
    return false;
}

/**
 * sort profiling data by wall time
 * PURE
 */
function prof_sort(array $a, array $b) : int {
    if ($a['wt'] == $b['wt']) { return 0; }
    return ($a['wt'] < $b['wt']) ? -1 : 1;
}


/**
 * replace file contents inline
 */
function file_replace(string $filename, string $find, string $replace) : bool {
    if (!file_exists($filename)) { 
        if (!touch($filename)) { return false; }
    }
    $in = file_get_contents($filename);
    \TF\debug("file replace [%s] [%s] in len [%d] ",$filename, $replace, strlen($in));
    $out = str_replace($find, $replace, $in);
    \TF\debug("file replace out len: " . strlen($out));
    return file_write($filename, $out);
}

/** boolean to string (true|false) */
function b2s(bool $input) :string {
    return ($input) ? "true" : "false";
}

function file_write(string $filename, string $content, $opts = LOCK_EX) : bool {
    $len = strlen($content);
    $result = (@file_put_contents($filename, $content, $opts) === $len);
    if (!$result) {
        \TF\debug("error write file [%s] len [%d]", $filename, $len);
    }
    return $result;
}


/**
 * load the ini file and cache the parsed code if possible
 * TODO: move initial config into separate function
 * NOT PURE
 */
function parse_ini(string $ini_src) : void {
    $config = array();
    $parsed_file = "$ini_src.php";
    if (file_exists($parsed_file) && filemtime($parsed_file) > filemtime($ini_src)) {
        require "$ini_src.php";
        CFG::set($config);
    } else {
        $config = parse_ini_file($ini_src, false, INI_SCANNER_TYPED);
        CFG::set($config);
        @chmod($parsed_file, 0644);
        if (CFG::enabled("cache_ini_files") && is_writable($parsed_file)) {
            if (!file_write($parsed_file, "<?php\n\$config=". var_export($config, true).";\n")) {
                if (is_writable($ini_src)) {
                    file_replace($ini_src, "cache_ini_files = true", "cache_ini_files = false");
                }
            }
        }
        // auto configuration
        if (CFG::disabled("configured")) {
            require_once WAF_DIR . "src/server.php";
            \BitFireSvr\update_config($ini_src);
        }
        @chmod($parsed_file, 0444);
    }

    check_pro_ver(CFG::str("pro_key"));
}

function check_pro_ver(string $pro_key) {
    // pro key and no pro files, download them UGLY, clean this!
    $profile = WAF_DIR . "src/proapi.php";
    if (strlen($pro_key) > 20 && !file_exists($profile) || (file_exists($profile) && @filesize(WAF_DIR."src/proapi.php") < 20)) {
        $out = WAF_DIR."src/pro.php";
        $content = \TF\bit_http_request("POST", "https://bitfire.co/getpro.php", array("release" => \BitFire\BITFIRE_VER, "key" => CFG::str("pro_key"), "file" => "pro.php"));
        \TF\debug("downloaded pro code [%d]", strlen($content));
        if ($content && strlen($content) > 100) {
            if (@file_put_contents($out, $content, LOCK_EX) !== strlen($content)) { \TF\debug("unable to write [%s]", $out); };
            $content = \TF\bit_http_request("POST", "https://bitfire.co/getpro.php", array("release" => \BitFire\BITFIRE_VER, "key" => CFG::str("pro_key"), "file" => "proapi.php"));
            \TF\debug("downloaded proapi code [%d]", strlen($content));
            $out = WAF_DIR."src/proapi.php";
            if ($content && strlen($content) > 100) {
                if (@file_put_contents($out, $content, LOCK_EX) !== strlen($content)) { \TF\debug("unable to write [%s]", $out); };
            }
        }
    }
}


// generate a random parameter value
function get_random_param(?string $name) : string {
    if (!$name) {
        return mt_rand(1000,9999) . "=" . \TF\random_str(6);
    }
    return "$name=" . \TF\random_str(6);
}


// return a cache busted url.  sets header Cache-Control: no-store
function cache_bust(?string $url="") : string {
    header("Cache-Control: no-store, private, no-cache, max-age=0");
    header('Expires: '.gmdate('D, d M Y H:i:s \G\M\T', 100000));

    if (CFG::enabled('cache_bust_parameter')) {
        $url = trim($url, " \t&?");
        return $url . ((strpos($url, '?') != false) ? "&" : '?') . get_random_param(CFG::str('cache_bust_parameter'));
    }
    return trim($url, '?&');
}

/**
 * add cache prevention headers
 */
function cache_prevent(\TF\Effect $effect) : \TF\Effect {
    $effect->header("cache-control", "no-store, private, no-cache, max-age=0");
    $effect->header("expires", gmdate('D, d M Y H:i:s \G\M\T', 100000));
    return $effect;
}

/**
 * add a random value for parameter named $param_name
 * PURE!
 */
function add_cache_bust_parameter(string $url, ?string $param_name=NULL) : string {
    if ($param_name) {
        $url_trim = trim($url, " \t&?");
        return $url_trim . ((strpos($url_trim, '?') != false) ? "&" : '?') . \TF\get_random_param($param_name);
    }
    return $url;
}

// return date in GMT time
function utc_date(string $format) : string {
    return date($format, utc_time());
}

function utc_time() : int {
    return time() + date('z');
}

function utc_microtime() : float {
    return time();
}

function array_shuffle(array $in) : array {
    $out = array();
    while(($m = count($in))>0) {
        $t = array_splice($in, mt_rand(0, $m), 1);
        $out[] = $t[0]??0;
    }
    return $out;
}

/**
 * returns a maybe with tracking data or an empty monad...
 * TODO: create test function
 * PURE!
 */
function decrypt_tracking_cookie(?string $cookie_data, string $encrypt_key, string $src_ip, string $agent) : \TF\MaybeStr {
    static $r = null;
    // don't bother decrypting if we have no cookie data
    if (empty($cookie_data)) { return \TF\MaybeStr::of(false); }
    if ($r === null) { $r = \TF\MaybeStr::of(false); }

    $r->doifnot(function() use ($cookie_data, $encrypt_key, $src_ip, $agent) {

        return \TF\decrypt_ssl($encrypt_key, $cookie_data)
            ->then("TF\\un_json")
            ->if(function($cookie) use ($src_ip, $agent) {
                if (!isset($cookie['wp']) && !isset($cookie['ip']) && !isset($cookie['lck'])) {
                    \TF\debug("invalid decrypted cookie [%s] ", var_export($cookie, true));
                    return false;
                } else if (isset($cookie['ip'])) {
                    $src_ip_crc = \BitFireBot\ip_to_int($src_ip);
                    $cookie_match = (is_array($cookie) && (intval($cookie['ip']??0) == intval($src_ip_crc)));
                    $time_good = ((intval($cookie['et']??0)) > time());
                    $agent_good = crc32($agent) == $cookie['ua'];
                    if (!$cookie_match) { \TF\debug("cookie ip does not match"); }
                    if (!$time_good) { \TF\debug("cookie expired"); }
                    if (!$agent_good) { \TF\debug("agent mismatch live: [%s] [%d] cookie:[%d]", $agent, crc32($agent), $cookie['ua']??0); }
                    return ($cookie_match && $time_good && $agent_good);
                } else { return true; }
            });
    });
    return $r;
}

