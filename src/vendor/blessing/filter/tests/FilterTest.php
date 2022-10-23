<?php

namespace Tests;

use Blessing\Filter;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

class FilterTest extends TestCase
{
    public function testAdd()
    {
        $filter = new Filter(new Container());
        $filter->add('hook', function () {
        });
        $filter->add('hook', function () {
        }, 10);
        $this->assertCount(2, $filter->getListeners('hook'));
    }

    public function testApply()
    {
        $filter = new Filter(new Container());
        $this->assertEquals('value', $filter->apply('hook', 'value', ['add']));

        $filter->add('hook', function ($value, $addition) {
            $this->assertEquals('add', $addition);

            return $value.'_medium';
        });
        $filter->add('hook', function ($value) {
            return $value.'_low';
        }, 10);
        $filter->add('hook', function ($value) {
            return $value.'_high';
        }, 30);
        $this->assertEquals('value_low_medium_high', $filter->apply('hook', 'value', ['add']));
    }

    public function testRemove()
    {
        $filter = new Filter(new Container());
        $filter->remove('hook');
        $this->assertCount(0, $filter->getListeners('hook'));

        $filter->add('hook', function () {
        });
        $this->assertCount(1, $filter->getListeners('hook'));
        $filter->remove('hook');
        $this->assertCount(0, $filter->getListeners('hook'));
    }

    public function testGetListeners()
    {
        $filter = new Filter(new Container());
        $this->assertCount(0, $filter->getListeners('hook'));
    }

    public function testResolveFromContainer()
    {
        $filter = new Filter(new Container());
        $filter->add('hook', FilterClass::class);

        $this->assertTrue($filter->apply('hook', 'value'));
    }
}
