<?php


namespace uukule\message\core;


use ArrayAccess,IteratorAggregate;

class Touser implements ArrayAccess,IteratorAggregate
{

    private $users = [];

    public function push($touser, $name = '')
    {
        array_push($this->users, [$touser, $name]);
        return $this;
    }


    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->users[] = $value;
        } else {
            $this->users[$offset] = $value;
        }
    }

    public function offsetExists($offset): bool
    {
        return isset($this->users[$offset]);
    }

    public function offsetUnset($offset): void
    {
        unset($this->users[$offset]);
    }

    public function offsetGet($offset)
    {
        return isset($this->users[$offset]) ? $this->users[$offset] : null;
    }

    /**
     * @return \Traversable
     */
    public function getIterator():\Traversable
    {
        return (function () {
            foreach($this->users as $key=>$val){
                yield $key => $val;
            }
        })();
    }
}