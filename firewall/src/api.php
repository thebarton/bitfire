<?php
/**
 * BitFire PHP based Firewall.
 * Author: BitFire (BitSlip6 company)
 * Distributed under the AGPL license: https://www.gnu.org/licenses/agpl-3.0.en.html
 * Please report issues to: https://github.com/bitslip6/bitfire/issues
 * 
 * all functions are called via api_call() from bitfire.php and all authentication 
 * is done there before calling any of these methods.
 */
namespace BitFire;

use ThreadFin\CacheStorage;
use ThreadFin\Effect;
use ThreadFin\FileData;
use ThreadFin\FileMod;
use ThreadFin\MaybeStr;
use BitFire\Config as CFG;
use RuntimeException;

use const ThreadFin\DAY;
use const ThreadFin\HOUR;

use function BitFireBot\find_ip_as;
use function BitFireSvr\add_ini_value;
use function BitFireSvr\update_ini_fn;
use function BitFireSvr\update_ini_value;
use function ThreadFin\machine_date;
use function ThreadFin\compact_array;
use function ThreadFin\contains;
use function ThreadFin\dbg;
use function ThreadFin\en_json;
use function ThreadFin\ends_with;
use function ThreadFin\file_recurse;
use function ThreadFin\find_fn;
use function ThreadFin\http2;
use function ThreadFin\httpp;
use function ThreadFin\partial_right as BINDR;
use function ThreadFin\partial as BINDL;
use function ThreadFin\random_str;
use function ThreadFin\un_json;
use function ThreadFin\debug;
use function ThreadFin\file_replace;
use function ThreadFin\trace;

require_once \BitFire\WAF_SRC . "server.php";
require_once \BitFire\WAF_SRC . "cms.php";


/**
 * block metrics
 */
class Metric {
    public $data = array();
    public $total = 0;
}

/**
 * make $dir_name if it does not exist, mode FILE_RW, 0755, etc
 * @impure
 * @return bool true if directory was newly created, or if it exists
 */
function make_dir(string $dir_name, int $mode) : bool {
    if (!file_exists(dirname($dir_name))) {
        return mkdir(dirname($dir_name), $mode, true);
    }
    return true;
}



/**
 * add an exception to exceptions.json
 * @pure
 * @API
 */
function rem_api_exception(\BitFire\Request $r) : Effect {
    assert(isset($r->post['uuid']), "uuid is required");
    $uuid = $r->post['uuid'];

    // an effect and the exception to add
    $effect = Effect::new();

    // load exceptions from disk
    $file = \BitFire\WAF_ROOT."exceptions.json";
    $exceptions = FileData::new($file)->read()->un_json();
    $removed = array_filter($exceptions(), function ($x) use ($uuid) {
        return ($x['uuid'] != $uuid);
    });

    // nothing added, exception already exists
    if (count($removed) == count($exceptions())) {
        $effect->api(false, "exception does not exist");
    }
    // new exception added
    else if (count($removed) < count($exceptions())) {
        $effect->api(true, "exception removed");
        $effect->file(new FileMod($file, json_encode($removed, JSON_PRETTY_PRINT), FILE_W));
    }
    // any other case
    else {
        $effect->api(false, "unable to remove exception from $file");
    }

    // return the result
    return $effect;
}

/**
 * add an exception to exceptions.json
 * @pure
 * @API
 */
function add_api_exception(\BitFire\Request $r) : Effect {
    assert(isset($r->post['path']), "path is required");
    assert(isset($r->post['code']), "code is required");
    $param = $r->post['param']??NULL;
    $r->post["action"] = "add_exception";
    httpp(APP."zxf.php", base64_encode(\ThreadFin\en_json($r->post)));

    // an effect and the exception to add

    // special handling of bot exceptions
    $class = code_class($r->post['code']);
    if ($class == 24000) {
        assert(isset($r->post['param']), "param is required");
        assert(isset($r->post['value']), "value is required");
        $value = $r->post['value']??NULL;

        $as = find_ip_as($value);
        $param_crc = crc32($param);
        $effect = update_ini_fn(function () use ($param_crc, $as, $value) { return "\n; bot exception from:[$value]\nbotwhitelist[$param_crc] = \"AS$as\"\n"; }, WAF_ROOT . "/cache/whitelist_agents.ini", true);

        //$effect = add_ini_value("botwhitelist[$param]", "AS{$as}", NULL, WAF_ROOT . "/cache/whitelist_agents.ini");
        $effect->api(true, "exception added");
        return $effect;
    }

    // all other exceptions, previous block returns...
    $effect = Effect::new();
    $ex = new \BitFire\Exception((int)$r->post['code'], random_str(8), $param, $r->post['path']);

    // load exceptions from disk
    $file = \BitFire\WAF_ROOT."exceptions.json";
    $exceptions = FileData::new($file)->read()->un_json()->map('\BitFire\map_exception');

    // add new exception (will not double add)
    $updated_exceptions = add_exception_to_list($ex, $exceptions());

    // nothing added, exception already exists
    if (count($updated_exceptions) == count($exceptions())) {
        $effect->api(false, "exception already exists");
    }
    // new exception added
    else if (count($updated_exceptions) > count($exceptions())) {
        $effect->api(true, "exception added");
        $effect->file(new FileMod($file, json_encode($updated_exceptions, JSON_PRETTY_PRINT), FILE_W));
    }
    // any other case
    else {
        $effect->api(false, "unable to add exception to $file");
    }

    // return the result
    return $effect;
}



/**
 * @pure
 * @param Request $r 
 * @return void 
 */
function download(\BitFire\Request $r) : Effect {
    assert(isset($r->get["filename"]), "filename is required");

	$effect = Effect::new();
    $root = \BitFireSvr\cms_root();
	$filename = trim($r->get['filename'], "/");
    $path = $root . $filename;

    // alert / block download
    if ($filename == "alert" || $filename == "block") {
        // TODO: move to server functions
        $config_name = ($filename == "alert") ? CONFIG_REPORT_FILE : CONFIG_BLOCK_FILE;
        $report_file = \ThreadFin\FileData::new(CFG::file($config_name))->read();
        $report_file->apply_ln('array_reverse')
            ->map('\ThreadFin\un_json');
        $data = json_encode($report_file->lines, JSON_PRETTY_PRINT);
        $filename .= ".json";
    }
	else {
        // FILE NAME GUARD
        if (! ends_with($filename, "php") || contains($filename, RESTRICTED_FILES)) {
            return $effect->api(false, "invalid file.");
        }
        // load data
        $file = FileData::new($filename);
        if (!$file->exists) {
            return $effect->api(false, "invalid file.");
        }
        $data = $file->raw();
    }

    if (!isset($r->get['direct'])) {
        $base = basename($filename);
        $effect->header("content-description", "File Transfer")
        ->header('Content-Type', 'application/octet-stream')
        ->header('Content-Disposition', 'attachment; filename="' . $base . '"')
        ->header('Expires', '0')
        ->header('Cache-Control', 'must-revalidate')
        ->header('Pragma', 'private')
        ->header('Content-Length', (string)strlen($data));
    }
    $effect->out($data);
    return $effect;
}

function malware_files(\BitFire\Request $request) : Effect {
    $effect = Effect::new();
    $malware_file = WAF_ROOT . "/cache/malware_files.json";
    $data = [
        "changed" => intval($request->post["changed"]),
        "unknown" => intval($request->post["unknown"]),
        "malware" => intval($request->post["malware"]),
        "time" => time()];
    $file = new FileMod($malware_file, en_json($data), FILE_RW);
    $effect->file($file);
    $effect->api(true, "malware files updated");
    return $effect; 
}

function archive_source(\BitFire\Request $request) : Effect {
    $effect = Effect::new();
    include_once WAF_SRC . "db.php";
    if (!defined("DB_USER")) {
        @include_once CFG::str("wp_root") . "wp-config.php";
    }
    $db_user     = defined( 'DB_USER' ) ? DB_USER : '';
	$db_password = defined( 'DB_PASSWORD' ) ? DB_PASSWORD : '';
	$db_name     = defined( 'DB_NAME' ) ? DB_NAME : '';
	$db_host     = defined( 'DB_HOST' ) ? DB_HOST : '';
    $credentials = new \ThreadFinDB\Credentials($db_user, $db_password, $db_host, $db_name);
    $out_stream = gzopen("bitfire.sql.gz", "wb6");
    $out_fn = BINDR('\ThreadFinDB\gz_output_fn', $out_stream);
    \ThreadFinDB\dump_database($credentials, $db_name, $out_fn);
    gzclose($out_stream);
    $num_bytes = $out_fun();
    $effect->api(true, "output $num_bytes bytes of SQL", ["bytes" => $num_bytes, "out_file" => "bitfire.sql.gz"]);
    return $effect;
}


/**
 * todo: deprecate and perform this function client side
 */
function diff(\BitFire\Request $request) : Effect {
    $root = \BitFireSvr\cms_root();
    if ($root == null) {
        return Effect::new()->api(false, "WordPress not found");
    }

    // invalid request...
    if (!isset($request->post['url']) || !isset($request->post['file_path'])) {
        return Effect::new()->api(false, "Invalid request.  Requires url and file_path parameters.");
    }

    // verify valid url
    $url = $request->post["url"];
    // TODO: move regex to plugin function
    if (!preg_match("/^https?:\/\/\w+\.svn.wordpress.org\//", $url)) {
        return Effect::new()->api(false, "invalid URL: $url");
    }
    // verify valid path
    $path = $request->post["file_path"];
    //if (!preg_match("#$root#", $path) || !ends_with($path, "php") || contains($path, "config")) {
    if (!ends_with($path, "php")) {
        return Effect::new()->api(false, "invalid file: $path");
    }
    $local_file = FileData::new($path);
    $local = $local_file->raw();
    $len = strlen($local);
    // hard coded WP files that are okay. todo: update with hashes
    if (
        //($len < 30 && contains($local, "is golden")) ||
        //(($len > 30000 && $len < 35000) && contains($local, "BitFire")) ||
        ($len == 2578 && md5($local) == "d945a3c574b70e3500c6dccc50eccc77")
    ) {
        $info = ["content" => $local, "success" => true];
    } else {
        $info = http2("GET", $url, "", [
            "User-Agent" => "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/101.0.4951.64 Safari/537.36",
            "Accept" => "*/*",
            "sec-ch-ua-platform" => "Linux",
            "upgrade-insecure-requests" => "1"]);

        // if we don't have a 200, then 0 out the 404 response.
        if ($info["length"] < 1 || (!in_array("http/1.1 200", $info["headers"]) && $info["http_code"] != 200)) { 
            $url2 = preg_replace("/\/tags\/[^\/]+\//", "/trunk/", $url);
            $info = http2("GET", $url2, "", [
                "User-Agent" => "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/101.0.4951.64 Safari/537.36",
                "Accept" => "*/*",//"text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng",
                "sec-ch-ua-platform" => "Linux",
                "upgrade-insecure-requests" => "1"]);

            // if we don't have a 200, then 0 out the 404 response.
            if ($info['length'] < 1 || (!in_array("http/1.1 200", $info["headers"]) && $info["http_code"] != 200)) { 
                $info["success"] = false; $info["content"] = "";
            }

        }
    }

    $success = $info["success"] && $local_file->exists;
    $data = array("url" => $request->post['url'], "file_path" => $request->post['file_path'], "compressed" => false);
    if (function_exists("zlib_encode")) {
        $data["zlib_local"] = base64_encode(zlib_encode($local, ZLIB_ENCODING_RAW));
        $data["zlib_orig"] = base64_encode(zlib_encode($info["content"], ZLIB_ENCODING_RAW));
        $data["compressed"] = true;
    } else {
        $data["local"] = base64_encode($local);
        $data["orig"] = base64_encode($info["content"]);
    }
    $effect = Effect::new()->api($success, "data", $data);
    return $effect;
}

// not DRY ripped from dashboard.php
function dump_hash_dir(\BitFire\Request $request) : Effect {
    $root = \BitFireSvr\cms_root();

    // for reading php files
    if (defined("BitFirePRO")) { stream_wrapper_restore("file"); }

    if (!empty($root) && isset($request->post['dir']) && strlen($request->post['dir']) > 1) { 
        $ver = trim($request->post['ver'], '/');
        $dir_path = realpath($request->post['dir']);
        $plugin_name = basename($dir_path);
        //FileData::new("{$dir_path}/readme.txt")->read()->apply_ln()

        $type_fn = find_fn("file_type");
        $hash_fn = BINDR('\BitFireSvr\hash_file2', $dir_path, $plugin_name, $type_fn);
        $hashes = file_recurse($dir_path, $hash_fn, '/.*.php/');
        $num_files = count($hashes);

        // no files to check!
        if ($num_files == 0) {
            return Effect::new()->api(true, "hashed $num_files", array("ver" => $ver, "basename" => basename($dir_path), "dir" => $request->post['dir'], "path" => $dir_path, "file_count" => $num_files, "hit_count" => $num_files, "success" => true, "data" => base64_encode(json_encode([]))));
        }

        $h2 = en_json(["ver" => $ver, "files" => $hashes]);
        $encoded = base64_encode($h2);

        $result = httpp(APP."hash_compare.php", $encoded, array("Content-Type" => "application/json"));
        $decoded = un_json($result);
        $c1 = count($decoded);
        debug(" [%s] sent $num_files hashes received $c1 hashes", $plugin_name);

        $dir_without_plugin_name = dirname($dir_path);


        $allowed = FileData::new(\BitFire\WAF_ROOT."cache/hashes.json")->read()->un_json()->lines;
        $allow_map = [];
        foreach ($allowed as $file) { $allow_map[$file["trim"]] = true; }

        // DEBUG LINE...
        //$path = str_replace("/", "_", $dir_path);
        //$b = file_put_contents("/tmp/dir_{$path}.txt", $result);
        //debug("wrote file /tmp/dir_{$path}.txt, ($b)");


        //echo "<pre>\n";
        //print_r($allow_map);
        // remove files that passed, (silence is golden)
        $filtered = array_filter($decoded, function ($file) {
            $r = $file['r']??'FAIL';
            // golden and dolly hashes
            $pass = $r !== "PASS";
            return $pass;
        });
        $num_miss = count($filtered);


        $filtered = array_filter($filtered, function ($file) use ($allow_map) {
            return !($allow_map[$file["crc_trim"]]??false);
        });


        // if the entire directory is unknown, squash it to a single entry
        if ($num_files == $num_miss && !$decoded[0]["found"]) {
            $compacted = compact_array($filtered);
            $sum = array_sum(array_map(function($x){return filesize($x['file_path']);}, $compacted));
            $sum_kb = round($sum/1024, 2);

            $compacted[0]["crc_trim"] = $hashes[0]->crc_trim;
            $compacted[0]["crc_path"] = $hashes[0]->crc_path;
            $compacted[0]["found"] = false;
            
            $compacted[0]["rel_path"] = (isset($hashes[0]->file_path)) ? basename($hashes[0]->file_path): "";
            $compacted[0]["file_path"] = $compacted[0]["rel_path"];
            $compacted[0]["mtime"] = filemtime($hashes[0]->file_path);
            $compacted[0]["machine_date"] = machine_date($compacted[0]["mtime"]);

            $type = $compacted[0]["type"]??"type";
            $name = $compacted[0]["name"]??$hashes[0]->name;
            $compacted[0]["known"] = "Unknown $type $name";
            $compacted[0]["bgclass"] = "bg-danger-soft";
            $compacted[0]["icon"] = "x";
            $compacted[0]["icon_class"] = "danger";
            $compacted[0]["kb2"] = "0 Files";
            $compacted[0]["kb1"] = count($compacted) . " Files ({$sum_kb} Kbytes)";

            $thing = isset($compacted[0]["table"]) ? $compacted[0]["table"] : $hashes[0]->type;
            $compacted[0]["table"] = "Unknown $thing";
            $compacted = [$compacted[0]];
        } else {
            $enrich_fn = BINDL('\BitFire\enrich_hashes', $ver, $dir_without_plugin_name);
            $enriched = array_map($enrich_fn, $filtered);
            $compacted = compact_array($enriched);
        }
        
        return Effect::new()->api(true, "hashed $num_files", array("ver" => $ver, "basename" => basename($dir_path), "dir" => $request->post['dir'], "path" => $dir_path, "file_count" => $num_files, "hit_count" => ($num_files - $num_miss), "success" => ($num_files == count($hashes)), "data" => base64_encode(json_encode($compacted))));
    }
    return Effect::new()->api(false, "server error. please upgrade BitFire.", array("success" => false, "data" => base64_encode('[]')));
}




/**
 * get 24 hour block sums
 */
function get_block_24sum() : array {
    $result = array();
    $cache = CacheStorage::get_instance();
    for($i=0; $i<25; $i++) {
        $data = $cache->load_data("metrics-$i", null);
        if ($data == null) { continue; }
        $sum = 0;
        foreach ($data as $code => $value) {
            if($code < 100000) { $sum += $value; }
        }
        $result[] = $sum;
    }
    
    return $result;
}

/**
 * get totals grouped by code
 */
function get_block_24groups() : Metric {
    $metric = new Metric();
    $cache = CacheStorage::get_instance();
    for($i=0; $i<25; $i++) {
        $data = $cache->load_data("metrics-$i", null);
        if ($data === null) { continue; }
        foreach ($data as $code => $cnt) {
            if ($code === "challenge" || $code === "valid") { continue; }
            if ($code < 100000 && $cnt > 0) { 
                $tmp = $metric->data[$code] ?? 0;
                $metric->data[$code] = $tmp + $cnt;
                $metric->total += $cnt;
            }
        }
    }
    return $metric;
}

function get_ip_24groups() : Metric {

    $total = 0;
    $summary = array();
    $cache = CacheStorage::get_instance();
    for($i=0; $i<25; $i++) {
        $data = $cache->load_data("metrics-$i", null);
        if ($data == null) { continue; }
        foreach ($data as $code => $cnt) {
            if ($code === "challenge" || $code === "valid") { continue; }
            if ($code > 100000 && $cnt > 0) { 
                $tmp = long2ip($code);
                $summary[$tmp] = ($summary[$tmp] ?? 0) + $cnt;
                $total += $cnt;
            }
        }
    }

    return parse_24_groups($summary, $total);
}

function parse_24_groups(array $summary, int $total) : \BitFire\Metric {
    
    $metric = new Metric();
    $metric->total = $total;

    uasort($summary, function ($a, $b) {
        if ($a == $b) { return 0; }
        return ($a < $b) ? -1 : 1;
    });

    if (count($summary) > 10) {
        $metric->data = array_slice($summary, 0, 10);
        $inc = array_sum(array_values(array_slice($summary, 10)));
        $metric->data['other'] = $inc;
    } else {
        $metric->data = $summary;
    }

    return $metric;
}


// FIX RESPONSE: 
function metrics_to_effect(Metric $metrics) : Effect {
    $effect = Effect::new();
    $per = array();
    if ($metrics->total > 0) {
        foreach ($metrics->data as $code => $value) { $per[$code] = (floor($value / $metrics->total) * 1000)/10; }
    } else {
        foreach ($metrics->data as $code => $value) { $per[$code] = 0; }
    }
    $effect->api(true, "", array("percent" => $per, "counts" => $metrics->data, "total" => $metrics->total));
    return $effect;
}

// FIX RESPONSE: 
function get_block_types(\BitFire\Request $request) : Effect {
    return (metrics_to_effect(get_block_24groups()));
}

// FIX RESPONSE: 
function get_hr_data(\BitFire\Request $request) : Effect {
    return (Effect::new()->api(true, "", get_block_24sum()));
}

// FIX RESPONSE: 
function get_ip_data(\BitFire\Request $request) : Effect {
    return (metrics_to_effect(get_ip_24groups()));
}

// FIX RESPONSE: 
function get_valid_data(\BitFire\Request $request) : Effect {
    $cache = CacheStorage::get_instance();
    $response = array('challenge' => 0, 'valid' => 0);
    for($i=0; $i<25; $i++) {
        $data = $cache->load_data("metrics-$i", null);
        if ($data === null) { 
            $cache->save_data("metrics-$i", $response, DAY);
            continue;
        }
        foreach ($data as $code => $cnt) {
            if ($code === "challenge") { $response['challenge'] += $cnt; }
            if ($code === "valid") { $response['valid'] += $cnt; }
        }
    }

    return Effect::new()->api(true, "", $response);
}

// create a new hmac code for validate_code
function make_code(string $secret) : string {
    $iv = strtolower(random_str(12));
    $time = time();
    $hash = hash_hmac("sha256", "{$iv}.{$time}", $secret, false);
    return "{$hash}.{$iv}.{$time}";
}

// validate hmac($iv.$time, $secret)  == $test_hmac, within 6 hours
function validate_raw(string $test_hmac, string $iv, string $time, string $secret) : bool {
    assert(strlen($secret) > 20, "secret key is too short");

    $d3 = hash_hmac("sha256", "{$iv}.{$time}", $secret, false);
    $d4 = hash_hmac("sha256", "{$iv}.{$time}", "default", false);

    $diff = time() - $time;
    //debug("hmac check [$diff] $d3 == $test_hmac");

    if ($diff > HOUR*6) {
        debug("hmac expired (6 hour maximum) [$diff] $test_hmac");
        return false;
    }
    return ($d4 === $test_hmac || $d3 === $test_hmac);
}

// validate $hash was generated with make_code($secret)
function validate_code(string $hash, string $secret) : bool {
    assert(strlen($secret) > 20, "secret key is too short");

    $validate_fn = BINDR("\BitFire\\validate_raw", $secret);

    $validator = MaybeStr::of($hash)
    ->then(BINDL("explode", "."))
    ->keep_if(BINDR("\ThreadFin\array_len", 3))
    ->then($validate_fn, true);

    return ($validator->value("bool") || false);
}

/**
 * download a BitFire release
 * @param string $version 
 * @return Effect 
 */
function download_tag(string $version, string $dest) : Effect {
    // download the archive TODO: check checksum
    $link = "https://github.com/bitslip6/bitfire/archive/refs/tags/{$version}.tar.gz";
    $resp_data = http2("GET", $link, "");
    $check_data = http2("GET", "https://bitfire.co/releases/{$version}.md5");
    $test_md5 = md5($resp_data["content"]);
    // checksum mismatch
    if ($test_md5 !== $check_data["content"]) {
        return Effect::new()->status(STATUS_ECOM);
    }
    return Effect::new()->status(STATUS_OK)->file(new FileMod($dest, $resp_data["content"]));
}

// only called for standalone installs, not plugins
function upgrade(\BitFire\Request $request) : Effect {
    $v = preg_replace("/[^0-9\.]/", "", $request->post['ver']);
    if (\version_compare($v, BITFIRE_SYM_VER, '<')) { 
        debug("version not current [%s]", $v);
        return Effect::new()->api(false, "version is not current");
    }

    // ensure that all files are writeable
    file_recurse(\BitFire\WAF_ROOT, function ($x) {
        if (!is_writeable($x)) { 
            return Effect::new()->api(false, "unable to upgrade: $x is not writeable");
        }
    });

    // allow php file manipulation
    stream_wrapper_restore("file");

    // download and verify no errors
    $dest = \BitFire\WAF_ROOT."cache/{$v}.tar.gz";
    $e = download_tag($v, $dest);
    $e->run();
    if ($e->num_errors() > 0) {
        return Effect::new()->api(false, "error downloading and saving release", $e->read_errors());
    }
    

    //  extract archive
    $target = \BitFire\WAF_ROOT . "cache";
    require_once \BitFire\WAF_SRC."tar.php";
    $success = \ThreadFin\tar_extract($dest, $target) ? "success" : "failure";
    

    // replace files
    file_recurse(\BitFire\WAF_ROOT."cache/bitfire-{$v}", function (string $x) use ($v) {
        $base = basename($x);
        if (is_file($x) && $base != "config.ini") {
            $root = str_replace(\BitFire\WAF_ROOT."cache/bitfire-{$v}/", "", $x);
            if (!rename($x, \BitFire\WAF_ROOT . $root)) { debug("unable to rename [%s] - %s", $x, $root); }
        }
    });

    $cwd = getcwd();
    CacheStorage::get_instance()->save_data("parse_ini2", null, -86400);
    return $effect->api($success, "upgraded with [$dest] in [$cwd]");
}

 
// FIX RESPONSE: 
function delete(\BitFire\Request $request) : Effect {

    $root = \BitFireSvr\cms_root();

    $effect = Effect::new();
    $f = $request->post['value'];

    if (stristr($f, "..") !== false) { return $effect->api(false, "refusing to delete relative path"); }

    if (strlen($f) > 1) {
        $out1 = $root . $f.".bak.".mt_rand(10000,99999);
        $src = $root . $f;

        if (!file_exists($src)) { return $effect->api(false, "refusing to delete relative path"); } 

        $quarantine_path = str_replace($root, \BitFire\WAF_ROOT."quarantine/", $out1);
        debug("moving [%s] to [%s]", $src, $quarantine_path);
        make_dir($quarantine_path, FILE_EX);
        if (!is_writable($src)) { chmod($src, FILE_RW); }
        if (is_writable($src)) {
            if (is_writeable($quarantine_path)) {
                $r = rename($src, "{$quarantine_path}{$f}");
                $effect->api(true, "renamed {$quarantine_path}{$f} ($r)");
            } else {
                $r = unlink($src);
                debug("unable to quarantine [$src] unlink:($r)");
                $effect->api(true, "deleted {$src} ($r)");
            }
        } else {
            debug("permission error quarantine [$src]");
            $effect->api(false, "delete permissions error '$src'");
        }
    } else {
        $effect->api(false, "no file to delete");
    }
   return  $effect;
}


function set_pass(\BitFire\Request $request) : Effect {
    $effect = Effect::new();
    debug("save pass");
    if (strlen($request->post['pass1']??'') < 8) {
        return $effect->api(false, "password is too short");
    }
    $p1 = hash("sha3-256", $request->post['pass1']??'');
    debug("pass sha3-256 %s ", $p1);
    $pass = file_replace(\BitFire\WAF_INI, "password = 'default'", "password = '$p1'")->run()->num_errors() == 0;
    CacheStorage::get_instance()->save_data("parse_ini2", null, -86400);
    exit(($pass) ? "success" : "unable to write to: " . \BitFire\WAF_INI);
}


// TODO: refactor UI to check api success value
function remove_list_elm(\BitFire\Request $request) : Effect {
    $effect = Effect::new();
    // guards
    if (!isset($request->post['config_name'])) { return $effect->api(false, "missing config parameter"); }
    if (!isset($request->post['config_value'])) { return $effect->api(false, "missing config value parameter"); }
    if (!isset($request->post['index'])) { return $effect->api(false, "missing index parameter"); }

    $v = substr($request->post['config_value'], 0, 80);
    $n = $request->post['config_name'];
    if (!in_array($n, \BitFireSvr\CONFIG_KEY_NAMES)) { return $effect->api(false, "unknown parameter name"); }

    $effect = update_ini_value("{$n}[]", "!", "$v");
    if ($effect->read_status() != STATUS_OK) {
        return $effect->api(false, "error updating ini status: " . $effect->read_status());
    }

    // SUCCESS!
    CacheStorage::get_instance()->save_data("parse_ini2", null, -86400);
    return $effect->api(true, "updated");
}

// modify to use FileData
// FIX RESPONSE: 
function add_list_elm(\BitFire\Request $request) : Effect {
    $effect = Effect::new();

    // guards
    if (!isset($request->post['config_name'])) { return $effect->api(false, "missing config parameter"); }
    if (!isset($request->post['config_value'])) { return $effect->api(false, "missing config value parameter"); }

    $value = substr($request->post['config_value'], 0, 80);
    $name = $request->post['config_name'];
    if (!in_array($name, \BitFireSvr\CONFIG_KEY_NAMES)) { return $effect->api(false, "unknown parameter name"); }

    $effect = add_ini_value("{$name}[]", $value)->api(true, "config.ini updated");
    CacheStorage::get_instance()->save_data("parse_ini2", null, -86400);
    return $effect;
}

// install always on protection (auto_prepend_file)
function install(?\BitFire\Request $request = null) : Effect {
    // CALL SERVER AND KEEP THIS CHECK HERE
    if (isset($_SERVER['IS_WPE'])) {
        $note = "WPEngine has a restriction which prevents that here.  Please go to WordPress plugin page and disable then re-enable this plugin to activate always-on.";
        return Effect::new()->exit(true, STATUS_FAIL, $note)->api(false, $note);
    }

    $effect = \BitFireSvr\install();
    CacheStorage::get_instance()->save_data("parse_ini2", null, -86400);
    return $effect;
}

// uninstall always on protection (auto_prepend_file)
function uninstall(\BitFire\Request $request) : Effect {
    CacheStorage::get_instance()->save_data("parse_ini2", null, -86400);
    return \BitFireSvr\uninstall();
}


function toggle_config_value(\BitFire\Request $request) : Effect {
    // handle fixing write permissions
    if ($request->post["param"] == "unlock_config") {
        $result = chmod(\BitFire\WAF_INI, 0664);
        return Effect::new()->api(true, "updated 2", ["file" => WAF_INI, "mode" => 0664, "result" => $result]);
    }
    // ugly fix for missing valid domain line
    $config = FileData::new(WAF_INI)->read()->filter(function($line) {
        return contains($line, "valid_domains[] = \"\"");
    });
    if ($config->num_lines < 1) {
        file_replace(WAF_INI, "; domain_fix_line", "valid_domains[] = \"\"\n; domain_fix_line")->run();
    }



    // update the config file
    $effect = \BitFireSvr\update_ini_value($request->post["param"], $request->post["value"]);
    // handle auto_start install
    if ($request->post["param"] == "auto_start") {
        $effect->chain(\BitFireSvr\install());
    } 
    $effect->api(true, "updated");
    CacheStorage::get_instance()->save_data("parse_ini2", null, -86400);
    return $effect;
}

/**
 * path is crc32 of path, trim is crc32 of trimmed content
 * @param Request $request 
 * @return Effect - the API response
 */
function allow(\BitFire\Request $request) : Effect {
    // preamble
    $file_name = \BitFire\WAF_ROOT . "cache/hashes.json";
    $effect = Effect::new();
    $data = un_json($request->post_raw);
    //debug("data\n%s", json_encode($data, JSON_PRETTY_PRINT));
    $path = intval($data['path']);
    $trim = intval($data['trim']);
    if (!file_exists($file_name)) { touch($file_name); }

    // load data and filter out this hash
    $file = FileData::new($file_name)
        ->read()
        ->un_json()
        ->filter(function($x) use ($trim, $path) { 
            return $x['path'] != $path && $x['trim'] != $trim;
        });
    //debug("file: " . json_encode($file, JSON_PRETTY_PRINT));

    // add the hash to the list
    $file->lines[] = [ "path" => $path, "trim" => $trim, "file" => $data["filename"]??'?' ]; 
    // all good, save the file
    $effect->file(new FileMod($file_name, en_json($file->lines)));
    //debug("effect: " . json_encode($effect, JSON_PRETTY_PRINT));


    // report any errors
    if (count($file->get_errors()) > 0) {
        return $effect->api(false, "error saving file allow list", $file->get_errors());
    }
    return $effect->api(true, "file added to allow list", ["id" => $trim]);
}


function clear_cache(\BitFire\Request $request) : Effect {
    CacheStorage::get_instance()->clear_cache();
    return \ThreadFin\cache_prevent()->api(true, "cache cleared");
}


function api_call(Request $request) : Effect {
    if (!isset($request->get[BITFIRE_COMMAND])) {
        die("api call NULL");
        return Effect::$NULL;
    }
    trace("api");

    $fn_name = htmlspecialchars($request->get[BITFIRE_COMMAND]);
    $fn = "\\BitFire\\$fn_name";
    if (!in_array($fn, BITFIRE_API_FN)) {
        die("api call no such $fn");
        return Effect::new()->exit(true, STATUS_ENOENT, "no such method");
    }

    if (file_exists(WAF_SRC."proapi.php")) { require_once \BitFire\WAF_SRC . "proapi.php"; }

    // verify admin password if user is not a CMS admin. will exit 401 and ask for auth if failure
    $admin_value = (BitFire::get_instance()->cookie->extract("wp")() > 1);
    if ($admin_value < 2) {
        $auth_effect = (function_exists("\\BitFirePlugin\\verify_admin_effect"))
            ? \BitFirePlugin\verify_admin_effect($request) 
            : verify_admin_password($request);
        $auth_effect->run();
    }
    

    $post = (strlen($request->post_raw) > 1 && count($request->post) < 1) ? un_json($request->post_raw) : $request->post;
    $code = (isset($post[BITFIRE_INTERNAL_PARAM])) 
        ? $post[BITFIRE_INTERNAL_PARAM]
        : $request->get[BITFIRE_INTERNAL_PARAM]??"";;

    if (trim($request->get["BITFIRE_API"]??"") != "send_mfa" && CFG::str("password") != "configure") {
        if (!validate_code($code, CFG::str("secret"))) {
            return Effect::new()->api(false, "invalid code", ["error" => "invalid / expired code"])->exit(true);
        }
        trace("SMFA");
    }

    $request->post = $post;
        
    $api_effect = $fn($request);
    assert($api_effect instanceof Effect, "api method did not return valid Effect");
    return $api_effect->exit(true);
}

