<?php

namespace BDG\DatabaseBundle\Tests\Controller;

interface InterfaceControllerTest 
{
    public function setUp();
    public function testLoadData();
    public function testNewScenario();
    public function testUpdateScenario();
    public function testShowHtmlScenario();
    public function testShowJsonScenario();
    public function testShowOneScenario();
    public function testDeleteScenario();
}