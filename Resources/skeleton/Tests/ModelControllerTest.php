<?php

namespace BDG\DatabaseBundle\Tests\Controller;


use Liip\FunctionalTestBundle\Test\WebTestCase as WebTestCase;


//http://jobeet.thuau.fr/testez-vos-formulaires
class ModelControllerTest extends WebTestCase
{
    static public $id  ;

    public function setUp()
    {          
        $this->sqlMax = 'SELECT max(e.id) from '.$this->bundle.':'.$this->entity.' e';
        
        $this->showHTML = "/admin/crud/$this->bundle/$this->entity/html/show";
        $this->showOne = "/admin/crud/$this->bundle/$this->entity/showone/";
        $this->showJSON = "/admin/crud/$this->bundle/$this->entity/json/show";

        $this->url_new = '/admin/crud/'.$this->bundle.'/'.$this->entity.'/new/';
        $this->url_edit = '/admin/crud/'.$this->bundle.'/'.$this->entity.'/edit/';
        $this->url_delete = '/admin/crud/'.$this->bundle.'/'.$this->entity.'/delete/';
        
        $this->PHP_AUTH_USER = 'A_MODIF_USER';
        $this->PHP_AUTH_PW = 'A_MODIF_PW';

        $this->namespace = 'A_MODIF_NAMESPACE';

        // environment permet de choisir le fichier config_test.yml
        $this->client = static::createClient(
            array( 'environment' => 'test','debug' => TRUE, ),
            array( 'PHP_AUTH_USER' => $this->PHP_AUTH_USER, 'PHP_AUTH_PW'   => $this->PHP_AUTH_PW, )
        );
        $this->kernel = static::createKernel(array('environment'=>'test', 'debug'=>TRUE) ); // ce sont les valeurs par defaut, donc pas utile, c'est juste pour se rappeler que cela peut etre changé
        $this->kernel->boot();
    }

    public function testloadData()
    {
        $fixtures = array( $this->namespace.'\Tests\Fixtures\Entity\Load'.$this->entity.'Data');
        $this->loadFixtures($fixtures);
    }

    public function testNewScenario($form, $crawler)
    {
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode(), "Unexpected HTTP status code for GET $this->url_new");
        
        $this->client->submit($form);
        
        $this->assertTrue($this->client->getResponse()->isRedirect($this->showHTML));
        
        unset($crawler);
        unset($form);

        $this->initId();

    }

    private function initId()
    {
        $em = $this->kernel->getContainer()->get('doctrine.orm.entity_manager');
        $query = $em->createQuery($this->sqlMax);
        
        self::$id = $query->getSingleScalarResult() > 0 ? $query->getSingleScalarResult() : 1;
    }
    public function testUpdateScenario()
    {
        $crawler = $this->client->request('GET', $this->url_edit.self::$id.'/');
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode(), "Unexpected HTTP status code for GET ".$this->url_edit.self::$id.'/');
        
        $form = $crawler->selectButton('Modifier')->form(array( ));

        $this->client->submit($form);

        $this->assertTrue($this->client->getResponse()->isRedirect($this->showHTML));

        unset($crawler);
        unset($form);
        gc_collect_cycles();
    }
    public function testShowHtmlScenario()
    {
        $crawler = $this->client->request('GET', $this->showHTML );
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode(), "Unexpected HTTP status code for GET ".$this->showHTML);

        unset($crawler);
        gc_collect_cycles();
    }
    public function testShowJsonScenario()
    {
        $crawler = $this->client->request('GET', $this->showJSON );
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode(), "Unexpected HTTP status code for GET ".$this->showJSON);

        unset($crawler);
        gc_collect_cycles();
    }
    public function testShowOneScenario()
    {
        $crawler = $this->client->request('GET', $this->showOne.self::$id.'/' );
        
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode(), "Unexpected HTTP status code for GET ". $this->showOne.self::$id.'/');
        unset($crawler);
        gc_collect_cycles();
    }
    public function testDeleteScenario()
    {
        $crawler = $this->client->request('GET', $this->url_delete.self::$id.'/');

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode(), "Unexpected HTTP status code for GET ".$this->url_delete.self::$id.'/');
        
        $form = $crawler->selectButton('Modifier')->form(array());
        
        $this->client->submit($form);

        $this->assertTrue($this->client->getResponse()->isRedirect($this->showHTML));

        unset($crawler);
        unset($form);
        gc_collect_cycles();
    }

}