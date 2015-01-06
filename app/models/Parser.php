<?php

class Parser
{
    private $lexer;

    public function __construct($dql)
    {
        $this->lexer = new Lexer($dql);
    }

    // ...

    public function getAST()
    {
        // Parse & build AST
        $AST = $this->QueryLanguage();

        // ...

        return $AST;
    }

    function appendField(&$fields, $key, $value)
    {
        if (is_null($key)) $key = 'any';
        if (is_null($value)) return false;
        $fields[] = array('key' => $key, 'value' => $value);
        return true;
    }

    public function QueryLanguage()
    {
        $this->lexer->moveNext();

        $fields = array();
        $fieldName = null;
        $fieldValue = null;
        $seenColon = false;
        echo "<pre>";

        while ($this->lexer->lookahead !== null) {
            // var_dump($this->lexer->lookahead);

            switch ($this->lexer->lookahead['type']) {
                case Lexer::T_IDENTIFIER:
                case Lexer::T_STRING:
                    if ($this->lexer->token['type'])
                    // print $this->lexer->lookahead['value'] . "<br>";
                    if ($this->appendField($fields, $fieldName, $fieldValue)) {
                        $fieldName = null;
                    }
                    $fieldValue = $this->lexer->lookahead['value'];
                    $seenColon = false;
                    break;
                case Lexer::T_COLON:
                    // print $this->lexer->lookahead['value'] . "<br>";
                    $fieldName = $fieldValue;
                    $fieldValue = null;
                    $seenColon = true;
                    break;
                default:
                    die('Parser: Unknown type found: ' . $this->lexer->lookahead['type']);
                    break;
            }
            $this->lexer->moveNext();
        }

        $this->appendField($fields, $fieldName, $fieldValue);

        var_dump($fields);
        die;

        switch ($this->lexer->lookahead['type']) {
            case Lexer::T_SELECT:
                $statement = $this->SelectStatement();
                break;
            case Lexer::T_UPDATE:
                $statement = $this->UpdateStatement();
                break;
            case Lexer::T_DELETE:
                $statement = $this->DeleteStatement();
                break;
            default:
                $this->syntaxError('SELECT, UPDATE or DELETE');
                break;
        }

        // Check for end of string
        if ($this->lexer->lookahead !== null) {
            $this->syntaxError('end of string');
        }

        return $statement;
    }

    // ...
}
