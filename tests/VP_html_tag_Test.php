<?php

require_once __DIR__ . '/../class/VP.php';

use PHPUnit\Framework\TestCase;

final class VP_html_tag_Test extends TestCase
{
    public function testTag()
    {
        $this->assertEquals(vp::html_tag('div', [], 'Hello World'), '<div>Hello World</div>');
        $this->assertEquals(vp::html_tag('div', [], ''), '');
        $this->assertEquals(vp::html_tag('div', [], '', true), '<div></div>');
        $this->assertEquals(vp::html_tag('FOO', [], '', true), '<FOO></FOO>');
        $this->assertEquals(vp::html_tag('foo-bar', [], '', true), '<foo-bar></foo-bar>');
        $this->assertEquals(vp::html_tag(' foo ', [], '', true), '<foo></foo>');
    }

    public function testEmptyTag()
    {
        $this->assertEquals(vp::html_tag(''), '');
        $this->assertEquals(vp::html_tag('', [], ''), '');
        $this->assertEquals(vp::html_tag('', [], '', true), '');
    }
    public function testSingletonTag()
    {
        $this->assertEquals(vp::html_tag('br'), '<br>');
        $this->assertEquals(vp::html_tag('input', [], 'foo'), '<input>');
        $this->assertEquals(vp::html_tag('link', [], 'foo', true), '<link>');
    }

    public function testAttributes()
    {
        $this->assertEquals(vp::html_tag('input', ['type' => 'text']), '<input type="text">');
        $this->assertEquals(vp::html_tag('div', ['id' => 'foo'], 'bar'), '<div id="foo">bar</div>');
        $this->assertEquals(vp::html_tag('div', ['id' => 'foo']), '');
        $this->assertEquals(vp::html_tag('div', ['id' => 'foo'], '', true), '<div id="foo"></div>');
    }

    public function testAllowEmptyContent()
    {
        $this->assertEquals(vp::html_tag('div', [], '', true), '<div></div>');
        $this->assertEquals(vp::html_tag('div', [], '', false), '');
        $this->assertEquals(vp::html_tag('div', [], ''), '');
    }
}
