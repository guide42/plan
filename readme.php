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
    $init = "include 'vendor/autoload.php';\n\n$init";
    return function(string $code) use($init): string {
        $data = system('php -r ' . escapeshellarg($init . $code), $ret);

        if ($ret !== 0) {
            throw new RuntimeException($data, $ret);
        }

        return $data;
    };
}

/** Parse and run a markdown file. */
function main(string $filename) {
    $tests = iterator_to_array(parse(file_get_contents($filename), 'php'));
    $init = array_shift($tests);
    $exec = runtime($init);

    foreach ($tests as $id => $test) {
        try {
            $exec($test);
            fwrite(STDOUT, "👍 $id\n");
        } catch (RuntimeException $ex) {
            fwrite(STDERR, "🔴 $id\n{$ex}\n\n");
            exit(1);
        }
    }
}

main($argv[1] ?? 'README.md');
