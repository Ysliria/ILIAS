<?php

abstract class Renderer {
    abstract public function render();
}

class EmptyRenderer extends Renderer {
    public function render() {
        return "";
    }
};

class CombinedRenderer extends Renderer {
    private $l; // Renderer
    private $r; // Renderer

    public function __construct(Renderer $left, Renderer $right) {
        $this->l = $left;
        $this->r = $right;
    }

    public function render() {
        return $l->render().$r->render();
    }
}

class ConstRenderer extends Renderer {
    private $content;

    public function __construct($content) {
        guardIsString($content);
        $this->content = $content;
    }

    public function render() {
        return $content;
    }
}


abstract class Collector {
}

class ConstCollector extends Collector {
    private $value;

    public function __construct($value) {
        $this->value = $value;
    }
}

class ApplyCollector extends Collector {
    private $l;
    private $r;

    public function __construct(Collector $left, Collector $right) {
        $this->l = $left;
        $this->r = $right;
    }
}

class EmptyCollector extends Collector {
}


class TypeError extends Exception {
    private $expected;
    private $found;

    public function __construct($expected, $found) {
        $this->expected = $expected;
        $this->found = $found;

        parent::__construct("Expected $expected, found $found...");
    }
}

function typeName($arg) {
    $t = getType($arg);
    if ($t == "object") {
        return get_class($arg);
    }
    return $t;
}

function guardIsString($arg) {
    if (!is_string($arg)) {
        throw new TypeError("string", typeName($arg));
    } 
}


abstract class FormletFactory {
    abstract static function instantiate($args);
}

abstract class Formlet {
    public abstract function build($name_source);
}

function verboseCheck_isFormlet($name, $args) {
    $name .= "Factory";
    $res = $name::instantiate($args)->build(0);
    return array
        ( "Renderer has correct instance"
            => $res["renderer"] instanceof Renderer
        , "Collector has correct instance"
            => $res["collector"] instanceof Collector
        , "Name source is integer."
            => is_int($res["name_source"])
        );
}

function andR($carry, $item) {
    return $carry && $item;
}

function _and($arr) {
    return array_reduce($arr, "andR", true);
}

function _o_f($val) {
    return $val?"OK":"FAIL"; 
}

function check_isFormlet($name, $args) {
    $res = verboseCheck_isFormlet($name, $args);
    return _and($res);
}

functin print_check_isFormlet($name, $args) {
    $res = verboseCheck_isFormlet($name, $args);
    echo "Checking $name:\n";
    foreach($res as $test => $result) {
        echo "\t$test: "._o_f($result)."\n";
    }
    echo "=> "._o_f(_and($res))."\n";
}

class PureValueFactory {
    public static function instantiate($args) {
        return new PureValue($args[0]); 
    }
}

class PureValue extends Formlet {
    private $value; // mixes

    public function __construct($value) {
        $this->value = $value;
    }

    public function build($name_source) {
        return array
            ( "renderer"    => new EmptyRenderer()
            , "collector"   => new ConstCollector($this->value)
            , "name_source" => $name_source
            );
    }
}

print_check_isFormlet("PureValue", array(42));
echo "\n";

class CombinedFormletsFactory {
    public static function instantiate($args) {
        return new CombinedFormlets($args[0], $args[1]);
    }
}

class CombinedFormlets {
    private $l; // Formlet
    private $r; // Formlet

    public function __construct(Formlet $left, Formlet $right) {
        $this->l = $left;
        $this->r = $right;
    }

    public function build($name_source) {
        $l = $this->l->build($name_source);
        $r = $this->r->build($l["name_source"]);
        return array
            ( "renderer"    => new CombinedRenderer($l["renderer"], $r["renderer"])
            , "collector"   => new ApplyCollector($l["collector"], $r["collector"]) 
            , "name_source" => $r["name_source"]
            );
    }
}

$pv = PureValueFactory::instantiate(1337);
print_check_isFormlet("CombinedFormlets", array($pv, $pv));
echo "\n";


class StaticSectionFactory {
    public static function instantiate($args) {
        return new StaticSection($args[0]);
    } 
}

class StaticSection {
    private $content; // string

    public function __construct($content) { 
        guardIsString($content);
        $this->content = $content;
    }

    public function build($name_source) {
        return array
            ( "renderer"    => new ConstRenderer($this->content)
            , "collector"   => new EmptyCollector()
            , "name_source" => $name_source
            );
    }
}

print_check_isFormlet("StaticSection", array("Hello World!"));
echo "\n";

/*abstract class NameSource {
    abstract public function getName();
}

abstract class Env {
}

abstract class Formlet {
    private $name_source; // NameSource

    public function __construct(NameSource $name_source) {
        $this->name_source = $name_source; 
    }

    abstract public function render();
    abstract public function collect(Env $env);
    public function apply(Formlet $other) {
        
    }
}

class PureValue extends Formlet {
    private $value; // mixed
    public function __construct($value) {
        $this->value = $value;
    }

    public function render() {
        return "";
    }

    public function collect(Env $env) {
        return $this->value;
    }
}
*/

?>

