<?php

namespace Deployer;

use Deployer\Utility\USymbol;
use Error;

class BashCommand
{
    private static $special_command;

    private static function __init_static()
    {
        if (!is_null(self::$special_command)) return;
        self::$special_command = USymbol::create('');
    }

    /**
     * Create a new command
     * @param string $name
     * @param string[] $arguments
     * @return BashCommand
     */
    public static function __callStatic($name, $arguments)
    {
        self::__init_static();
        return new self($name, ...$arguments);
    }
    /**
     *
     * @param (string|BashCommand)[] $raw_command
     * @return BashCommand
     */
    public static function raw(...$raw_command)
    {
        self::__init_static();
        return new self(self::$special_command, 'raw', ...$raw_command);
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
        if (empty($opts)) return self::raw("true");
        return self::raw("set " . implode(" ", $opts));
    }
    private static function batchJoiner(string $join, BashCommand ...$commands)
    {
        $new_commands = [
            self::raw("( "),
        ];
        foreach ($commands as $c) {
            if (!empty($new_commands)) $new_commands[] = self::raw(" )$join( ");
            $new_commands[] = $c;
        }
        $new_commands[] = self::raw(" )");
        return self::raw(...$new_commands);
    }
    /**
     *
     * @param BashCommand[] $commands
     * @return BashCommand
     */
    public static function batch(BashCommand ...$commands)
    {
        return self::batchJoiner(";", ...$commands);
    }
    /**
     *
     * @param BashCommand[] $commands
     * @return BashCommand
     */
    public static function and(BashCommand ...$commands)
    {
        return self::batchJoiner("&&", ...$commands);
    }
    /**
     *
     * @param BashCommand[] $commands
     * @return BashCommand
     */
    public static function pipe(BashCommand ...$commands)
    {
        return self::batchJoiner("|", ...$commands);
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
            return self::raw('"$( ', $command, ' )"');
        } else {
            return self::raw('$( ', $command, ' )');
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
        return self::raw('"$(' . self::echo(self::raw(bin2hex($arg))) . ' | xxd -r -p)"');
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
        return $this->_args[0] === self::$special_command;
    }
    private function escapeArg($arg)
    {
        if (is_string($arg)) return self::escapeStringArg($arg);
        if ($arg instanceof self) return (string)$arg;
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
                default:
                    throw new Error("Should not occur: special command::__toString(): " . $this->_args[1]);
            }
        }
        return self::escapeStringArg($this->__toString());
    }
    public function __toString(): string
    {
        if ($this->isRawCommand()) {
            switch ($this->_args[1]) {
                case 'raw':
                    return implode("", array_slice($this->_args, 2));
                default:
                    throw new Error("Should not occur: special command::__toString(): " . $this->_args[1]);
            }
        }
        $result = "";
        $last_is_raw = false;
        foreach ($this->_args as $arg) {
            $part = $this->escapeArg($arg);
            $current_is_raw = $arg instanceof self && $arg->isRawCommand();
            if (!empty($result) && !$last_is_raw && !$current_is_raw) $result .= " ";
            $result .= $part;
        }
        if (!empty($this->_streams)) $result .= " " . implode(" ", $this->_streams);
        if (!empty($this->_controls)) $result .= " " . implode(" ", $this->_controls);
        return $result;
    }
}
