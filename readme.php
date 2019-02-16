<?php

include 'vendor/autoload.php';

/** Parses the content of markdown and extract the code blocks, optional of
 *  specific language. */
function parse(string $markdown, string $lang=null): Generator {
    preg_match_all("/(.*)\n(.+)\n\n```$lang([^`]*)```/", $markdown, $matches);
    foreach ($matches[3] as $i => $code) {
        yield trim(ltrim($matches[1][$i], '*') . ' ' . rtrim($matches[2][$i], ':')) => $code;
    }
}

/** Creates a PHP code run environment. */
function runtime(string $init): callable {
    static $cmd = 'php -dzend.assertions=1 -dassert.active=1 -dassert.quiet_eval=0 -dassert.bail=1 -dassert.warning=1';
    $init = "include 'vendor/autoload.php';\n\n$init";
    return function(string $code) use($cmd, $init): string {
        $cmd .= ' -r ' . escapeshellarg($init . $code);
        $out = system($cmd, $ret);

        if ($ret !== 0) {
            throw new RuntimeException($data, $ret);
        }

        return $out;
    };
}

/** Parse and run a markdown file. */
function main(string $filename) {
    $tests = iterator_to_array(parse(file_get_contents($filename), 'php'));
    $init = array_shift($tests);
    $exec = runtime($init);

    foreach ($tests as $id => $test) {
        try {
            $out = $exec($test);
            fwrite(STDOUT, "👍 $id\n");
            if (!empty($out)) {
                fwrite(STDOUT, "🐞 $out\n\n");
            }
        } catch (RuntimeException $ex) {
            fwrite(STDERR, "🔴 $id\n{$ex}\n\n");
        }
    }
}

main($argv[1] ?? 'README.md');
