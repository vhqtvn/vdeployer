<?php

namespace Deployer;

use Deployer\Utility\USymbol;
use Error;

class BashCommand
{
    private static $special_command;

    private static function __init_static()
    {
        if (!is_null(static::$special_command)) return;
        static::$special_command = USymbol::create('');
    }

    /**
     * Create a new command
     * @param string $name
     * @param string[] $arguments
     * @return BashCommand
     */
    public static function __callStatic($name, $arguments)
    {
        static::__init_static();
        return new static($name, ...$arguments);
    }
    /**
     *
     * @param (string|BashCommand)[] $raw_command
     * @return BashCommand
     */
    public static function raw(...$raw_command)
    {
        static::__init_static();
        return new static(static::$special_command, 'raw', ...$raw_command);
    }
    /**
     *
     * @return BashCommand
     */
    public static function nil()
    {
        static::__init_static();
        return new static(static::$special_command, 'nil');
    }
    /**
     *
     * @param (string|BashCommand)[] $raw_command
     * @return BashCommand
     */
    public static function rawArg(...$raw_command)
    {
        static::__init_static();
        $raw_command[] = static::raw(' ');
        return new static(
            static::$special_command,
            'raw',
            static::raw(' '),
            ...$raw_command
        );
    }
    public static function arg($name, ...$args)
    {
        static::__init_static();
        return new static($name, ...$args);
    }
    public static function setopt(
        $verbose = null,
        $xtrace = null,
        $pipefail = null,
        $noglob = null,
        $errexit = null,
    ) {
        $opts = [];
        if (!is_null($errexit)) $opts[] = $errexit ? "-e" : "+e";
        if (!is_null($noglob)) $opts[] = $noglob ? "-f" : "+f";
        if (!is_null($pipefail)) $opts[] = $pipefail ? "-o pipefail" : "+o pipefail";
        if (!is_null($verbose)) $opts[] = $verbose ? "-v" : "+v";
        if (!is_null($xtrace)) $opts[] = $xtrace ? "-x" : "+x";
        if (empty($opts)) return static::raw("true");
        return static::raw("set " . implode(" ", $opts));
    }
    private static function batchJoiner(string $join, BashCommand ...$commands)
    {
        $new_commands = [
            static::raw("( "),
        ];
        foreach ($commands as $c) {
            if (!empty($new_commands)) $new_commands[] = static::raw(" )$join( ");
            $new_commands[] = $c;
        }
        $new_commands[] = static::raw(" )");
        return static::raw(...$new_commands);
    }
    /**
     *
     * @param BashCommand[] $commands
     * @return BashCommand
     */
    public static function batch(BashCommand ...$commands)
    {
        return static::batchJoiner(";", ...$commands);
    }
    /**
     * Combine all the commands by &&
     * @param BashCommand[] $commands
     * @return BashCommand
     */
    public static function all(BashCommand ...$commands)
    {
        return static::batchJoiner("&&", ...$commands);
    }
    /**
     * Combine all the commands by ||
     * @param BashCommand[] $commands
     * @return BashCommand
     */
    public static function first(BashCommand ...$commands)
    {
        return static::batchJoiner("||", ...$commands);
    }
    /**
     *
     * @param BashCommand[] $commands
     * @return BashCommand
     */
    public static function pipe(BashCommand ...$commands)
    {
        return static::batchJoiner("|", ...$commands);
    }

    /**
     *
     * @param BashCommand $command
     * @param boolean|bool $quote wrap the sub-shell inside "..."
     * @return BashCommand
     */
    public static function subShell(BashCommand $command, bool $quote = false)
    {
        if ($quote) {
            return static::raw('"$( ', $command, ' )"');
        } else {
            return static::raw('$( ', $command, ' )');
        }
    }

    private static function escapeStringArg(string $arg)
    {
        $is_normal_string = true;
        for ($i = 0, $ie = strlen($arg); $i < $ie; $i++) {
            $code = ord($arg[$i]);
            if ($code < 0x20 || $code >= 0x80) {
                $is_normal_string = false;
            }
        }
        if ($is_normal_string) return escapeshellarg($arg);
        return static::raw('"$(' . static::echo(static::raw(bin2hex($arg))) . ' | xxd -r -p)"');
    }


    private $_args;
    private $_streams;
    private $_controls;
    private function __construct($command, ...$args)
    {
        $this->_args = [$command, ...$args];
        $this->_streams = [];
        $this->_controls = [];
    }
    /**
     * @return $this
     */
    public function pipeOut2Err()
    {
        $this->_streams[] = "2>&1";
        return $this;
    }
    /**
     * @return $this
     */
    public function bg()
    {
        $this->_controls[] = "&";
        return $this;
    }
    public function isRawCommand()
    {
        return $this->_args[0] === static::$special_command && $this->_args[1] == "raw";
    }
    public function isNilCommand()
    {
        return $this->_args[0] === static::$special_command && $this->_args[1] == "nil";
    }
    public function ignoreError()
    {
        return static::first($this, static::true());
    }
    private function escapeArg($arg)
    {
        if (is_string($arg)) return static::escapeStringArg($arg);
        if ($arg instanceof static) return (string)$arg;
        if (is_integer($arg)) return "$arg";
        if (is_bool($arg)) return $arg ? "1" : "0";
        throw new Error("Unexpected argument: " . var_export($arg, true));
    }
    private function toEscapedArgument()
    {
        if ($this->isRawCommand()) {
            switch ($this->_args[1]) {
                case 'raw':
                    return $this->_args[2];
                case 'nil':
                    return null;
                default:
                    throw new Error("Should not occur: special command::__toString(): " . $this->_args[1]);
            }
        }
        return static::escapeStringArg($this->__toString());
    }
    public function __toString(): string
    {
        if ($this->isRawCommand()) {
            switch ($this->_args[1]) {
                case 'raw':
                    return implode("", array_slice($this->_args, 2));
                case 'nil':
                    return "";
                default:
                    throw new Error("Should not occur: special command::__toString(): " . $this->_args[1]);
            }
        }
        $result = "";
        $last_is_raw = false;
        foreach ($this->_args as $arg) {
            if ($arg instanceof self && $arg->isNilCommand()) continue;
            $part = $this->escapeArg($arg);
            $current_is_raw = $arg instanceof self && $arg->isRawCommand();
            if (!empty($result) && !$last_is_raw && !$current_is_raw) $result .= " ";
            $last_is_raw = $current_is_raw;
            $result .= $part;
        }
        if (!empty($this->_streams)) $result .= " " . implode(" ", $this->_streams);
        if (!empty($this->_controls)) $result .= " " . implode(" ", $this->_controls);
        return $result;
    }
}
