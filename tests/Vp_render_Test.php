<?php

require_once __DIR__ . '/../class/VP.php';

use PHPUnit\Framework\TestCase;

final class Vp_render_Test extends TestCase
{
    public function testScalar()
    {
        $this->assertEquals(vp::render(''), '');
        $this->assertEquals(vp::render('a'), 'a');
        $this->assertEquals(vp::render('0'), '0');
        $this->assertEquals(vp::render(0), '0');
        $this->assertEquals(vp::render(42), '42');
        $this->assertEquals(vp::render(42.42), '42.42');
        $this->assertEquals(vp::render(true), '1');
        $this->assertEquals(vp::render(false), '');
        $this->assertEquals(vp::render(null), '');
    }

    public function testIndexedArray()
    {
        $this->assertEquals(vp::render(['a']), 'a');
        $this->assertEquals(vp::render(['a', 'b']), 'ab');
        $this->assertEquals(vp::render([['a', 'b'], 'c']), 'abc');
    }

    public function testAssociativeArray()
    {
        $this->assertEquals(vp::render(['test' => 'a']), '');
        $this->assertEquals(vp::render(['tag' => 'a']), '');
        $this->assertEquals(vp::render(['tag' => 'a'], true), '<a></a>');
        $this->assertEquals(vp::render(['tag' => 'a', 'allow_empty_content' => true]), '<a></a>');
        $this->assertEquals(vp::render(['tag' => 'input']), '<input>');
        $this->assertEquals(vp::render(['tag' => 'input', 'type' => 'text']), '<input type="text">');
        $this->assertEquals(vp::render(['tag' => 'input', 'type' => 'text', 'tabindex' => '0']), '<input type="text" tabindex="0">');
        $this->assertEquals(vp::render(['tag' => 'a', 'content' => 'b']), '<a>b</a>');
        $this->assertEquals(vp::render(['tag' => 'a', 'content' => '0']), '<a>0</a>');
        $this->assertEquals(vp::render(['tag' => 'a', 'content' => ['b', 'c']]), '<a>bc</a>');
        $this->assertEquals(vp::render(['tag' => 'a', 'content' => ['tag' => 'b', 'content' => 'c']]), '<a><b>c</b></a>');
    }
}
