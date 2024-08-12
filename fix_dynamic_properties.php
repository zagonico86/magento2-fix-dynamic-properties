<?php
use Magento\Framework\App\Bootstrap;

require __DIR__ . '/app/bootstrap.php';

$params = $_SERVER;
$bootstrap = Bootstrap::create(BP, $params);

$obj = $bootstrap->getObjectManager();

$state = $obj->get('Magento\Framework\App\State');
$state->setAreaCode('adminhtml');

//////////////////////// PARAMETERS

if ($argc==1 || ($argc>1 && ($argv[1]=='-h' || $argv[1]=='--help'))) {
	echo "Syntax:\n    ".$argv[0]." <verbose> <solve> <directory> <only_this>\n\n";
	echo "- verbose: 0 only show the modifications done, 1 show more info on the source analyzed\n";
	echo "- solve: 0 does not solve the errors found, 1 solve the errors found\n";
	echo "- directory: directory that will be recursively solved\n";
	echo "- only_this: if specified only analyze this file\n";
	echo "\n";
	echo "Example to only simulate modifications:\n    ".$argv[0]." 0 0 app/code/MyModule/\n\n";
	echo "Example to solve problems:\n    ".$argv[0]." 0 1 app/code/MyModule/\n";
	die();
}

$log_all = false;
if ($argc > 1) {
    $log_all = $argv[1]!='0';
}

$solve = false;
if ($argc > 2) {
    $solve = $argv[2]!='0';
}

$directory = '.';
if ($argc > 3 && $argv[3] != '0') {
    $directory = $argv[3];
}

$only_this = '';
if ($argc > 4 && $argv[4] != '0') {
    $only_this = $argv[4];
}
////////////////

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
$phpFiles = new RegexIterator($iterator, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

function readFromLineToLine($filename, $startLine, $endLine) {
    $f = fopen($filename, 'r');
    $lineNo = 0;
    $result = '';

    while ($line = fgets($f)) {
        $lineNo++;
        if ($lineNo >= $startLine) {
            $result .= $line;
        }
        if ($lineNo == $endLine) {
            break;
        }
    }
    fclose($f);
    return $result;
}

function getConstructorVariables($constructor) {
    $pattern = '/\$this->(\w+)/';
    $matches = [];

    if (preg_match_all($pattern, $constructor, $matches)) {
        return array_unique($matches[1]);
    }

    return [];
}

function getConstructorParams($constructor) {
    $params = $constructor->getParameters();
    $result = [];

    foreach ($params as $param) {
        $type = $param->getType();
        $typeName = $type instanceof ReflectionNamedType ? $type->getName() : 'mixed';
        $result[$param->getName()] = $typeName;
    }

    return $result;
}

function getClassProperties($reflector) {
    $prop = [];
    foreach ($reflector->getProperties() as $property) {
        $prop[] = $property->getName();
    }
    return $prop;
}

function insertVariables($filename, $reflector, $partToBeInserted) {
    $initialPart = readFromLineToLine($filename, 0, $reflector->getStartLine()+1);
    $endPart = readFromLineToLine($filename, $reflector->getStartLine()+2, 0);

    file_put_contents($filename, $initialPart.$partToBeInserted.$endPart);
}

foreach ($phpFiles as $file) {
    $filePath = $file[0];

    if ($only_this && $filePath != $only_this) continue;

    $fileContent = file_get_contents($filePath);

    $variablesToBeAdded = '';

    preg_match('/namespace\s+([^;]+);/', $fileContent, $namespaceMatches);
    $namespace = $namespaceMatches[1] ?? 'Global namespace';

    preg_match('/\nclass\s+([^{\s]+)/', $fileContent, $classMatches);
    $className = $classMatches[1] ?? null;

    $containContructor = strpos($fileContent, '__construct')!==false;

    if ($className) {
        $fullClassName = '\\'.$namespace . '\\' . $className;
        if ($log_all) echo "File: $filePath\n";
        if ($log_all) echo "Namespace: $namespace\n";
        if ($log_all) echo "Class: $className\n";
        if (!$containContructor) {
            if ($log_all) echo "CONSTRUCTOR MISSING\n";
            continue;
        }

        $reflector = new ReflectionClass($fullClassName);

        $constructor = $reflector->getConstructor();
        if ($constructor) {
            if ($log_all) echo "Constructor position: ".$constructor->getStartLine(). " - ". $constructor->getEndLine()."\n";
            $constructorText = readFromLineToLine($filePath, $constructor->getStartLine(), $constructor->getEndLine());
            if ($log_all) echo "Constructor Text: \n----\n" . $constructorText . "\n----\n";

            $classProperties = getClassProperties($reflector);
            $constructorVariables = getConstructorVariables($constructorText);
            $constructorParams = getConstructorParams($constructor);

            foreach ($constructorVariables as $var) {
                if (!in_array($var, $classProperties)) {
                    if ($log_all) echo $var." used in constructor but not declared in class\n";
                    $variablesToBeAdded .= "\t/** @var ".(isset($constructorParams[$var]) ? "\\".$constructorParams[$var] : 'mixed')." */\n\tprotected ".'$'.$var.";\n\n";
                }
                else {
                    if ($log_all) echo  $var." present in class declared variables\n";
                }
            }

            if ($variablesToBeAdded) {
                if (!$log_all) echo "File: $filePath\n";
                echo "Missing declarations:\n".$variablesToBeAdded;

                if ($solve) insertVariables($filePath, $reflector, $variablesToBeAdded);
            }
            else {
                if ($log_all) echo "No declaration is missing\n";
            }
        } else {
            if ($log_all) echo "No constructor found.\n";
        }
        if ($log_all) echo "\n";
    }
    else {
        if ($log_all) echo "File: $filePath NOT A CLASS\n";
    }
}
