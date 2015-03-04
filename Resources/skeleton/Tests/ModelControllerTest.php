<?php

namespace BDG\DatabaseBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

//http://jobeet.thuau.fr/testez-vos-formulaires
class ModelControllerTest extends WebTestCase
{
    public static $ident;

    public function setUp()
    {
        $this->sqlMax = 'SELECT max(e.id) from '.$this->bundle.':'.$this->entity.' e';

        $this->showHTML = "/admin/crud/$this->bundle/$this->entity/html/show";
        $this->showOne = "/admin/crud/$this->bundle/$this->entity/showone/";
        $this->showJSON = "/admin/crud/$this->bundle/$this->entity/json/show";

        $this->urlNew = '/admin/crud/'.$this->bundle.'/'.$this->entity.'/new/';
        $this->urlEdit = '/admin/crud/'.$this->bundle.'/'.$this->entity.'/edit/';
        $this->urlDelete = '/admin/crud/'.$this->bundle.'/'.$this->entity.'/delete/';

        $this->PHP_AUTH_USER = 'A_MODIF_USER';
        $this->PHP_AUTH_PW = 'A_MODIF_PW';

        $this->namespace = 'A_MODIF_NAMESPACE';

        // environment permet de choisir le fichier config_test.yml
        $this->client = static::createClient(
            array( 'environment' => 'test', 'debug' => true),
            array( 'PHP_AUTH_USER' => $this->PHP_AUTH_USER, 'PHP_AUTH_PW'   => $this->PHP_AUTH_PW)
        );
        $this->kernel = static::createKernel(array('environment' => 'test', 'debug' => true)); // ce sont les valeurs par defaut, donc pas utile, c'est juste pour se rappeler que cela peut etre changé
        $this->kernel->boot();
    }

    public function testloadData()
    {
        // $fixtures = array( $this->namespace.'\Tests\Fixtures\Entity\Load'.$this->entity.'Data');
        // $this->loadFixtures($fixtures);
    }

    public function testAuditScenario()
    {
        $crawler = $this->client->request('GET', $this->showHTML);
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode(), "Unexpected HTTP status code for GET ".$this->showHTML);
        $this->assertTrue($crawler->filter('h3:contains("Audit")')->count() > 0);
    }

    public function testNewScenario($form, $crawler)
    {
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode(), "Unexpected HTTP status code for GET $this->url_new");
        $this->client->submit($form);
        $this->assertTrue($this->client->getResponse()->isRedirect($this->showHTML));

        $this->initId();
    }

    private function initId()
    {
        $eManager = $this->kernel->getContainer()->get('doctrine.orm.entity_manager');
        $query = $eManager->createQuery($this->sqlMax);

        self::$ident = $query->getSingleScalarResult() > 0 ? $query->getSingleScalarResult() : 1;
    }
    public function testUpdateScenario()
    {
        $crawler = $this->client->request('GET', $this->url_edit.self::$ident.'/');
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode(), "Unexpected HTTP status code for GET ".$this->url_edit.self::$ident.'/');
        $form = $crawler->selectButton('Modifier')->form(array( ));
        $this->client->submit($form);
        $this->assertTrue($this->client->getResponse()->isRedirect($this->showHTML));
    }
    public function testShowHtmlScenario()
    {
        $crawler = $this->client->request('GET', $this->showHTML);
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode(), "Unexpected HTTP status code for GET ".$this->showHTML);
    }
    public function testShowJsonScenario()
    {
        $crawler = $this->client->request('GET', $this->showJSON);
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode(), "Unexpected HTTP status code for GET ".$this->showJSON);
    }
    public function testShowOneScenario()
    {
        $crawler = $this->client->request('GET', $this->showOne.self::$ident.'/');
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode(), "Unexpected HTTP status code for GET ".$this->showOne.self::$ident.'/');
    }
    public function testDeleteScenario()
    {
        $crawler = $this->client->request('GET', $this->url_delete.self::$ident.'/');
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode(), "Unexpected HTTP status code for GET ".$this->url_delete.self::$ident.'/');
        $form = $crawler->selectButton('Supprimer')->form(array());
        $this->client->submit($form);
        $this->assertTrue($this->client->getResponse()->isRedirect($this->showHTML));

        unset($crawler);
        unset($form);
        gc_collect_cycles();
    }
}
