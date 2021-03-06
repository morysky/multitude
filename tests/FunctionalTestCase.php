<?php

use App\Console\Kernel;
use Mongolid\Connection\Pool;

class FunctionalTestCase extends TestCase
{
    /**
     * Used for the visual reporting at tearDownAfterClass
     * @var string
     */
    public static $testOutput;

    public function setUp()
    {
        parent::setUp();

        $this->runCommand('db:searchindex');
        $this->waitElasticsearchOperations();
    }

    /**
     * Creates the application.
     *
     * @return \Laravel\Lumen\Application
     */
    public function createApplication()
    {
        return require __DIR__.'/../bootstrap/app.php';
    }

    /**
     * Asserts if the given content is present in the last response
     * received
     *
     * @param  string $content  Needle
     *
     * @return FunctionalTestCase  Self
     */
    protected function see($content)
    {
        $this->assertContains(
            $content,
            $this->response->getContent(),
            "Couldn't find $content in response content."
        );

        return $this;
    }

    /**
     * Runs an artisan command by it's name
     *
     * @param  string $command Command name
     * @param  array  $params  Command params
     *
     * @return FunctionalTestCase  Self
     */
    protected function runCommand($command, $params = [])
    {
        $this->app->make(Kernel::class)->call($command, $params);

        return $this;
    }

    /**
     * Calls the Refresh API of Elasticsearch. Making indexed documents ready
     * to be queryied.
     *
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/2.3/indices-refresh.html
     */
    protected function waitElasticsearchOperations()
    {
        $indiceName = $this->app->make('config')->get('elasticsearch.defaultIndex');

        $this->app->make(\Elasticsearch\Client::class)
            ->indices()->refresh(['index' => $indiceName]);

        usleep(2 * 1000);
    }

    /**
     * Drops an database collection
     *
     * @param  string $collectionName Name of the collection being dropped.
     *
     * @return void
     */
    protected function cleanCollection(string $collectionName)
    {
        $conn = $this->app->make(Pool::class)->getConnection();
        $db = $conn->getRawConnection()->{$conn->defaultDatabase};
        $db->$collectionName->drop();
    }

    /**
     * Prepares the test output based in the @feature annotation or Class name.
     *
     * @return void
     */
    public static function setUpBeforeClass()
    {
        $docs = (new ReflectionClass(static::class))->getDocComment();
        preg_match('/@feature ([^@\/]+)/', $docs, $matches);
        $featureAnnotation = trim(str_replace('*', '', preg_replace('/ +/', ' ', $matches[1] ?? "")));

        echo "\n ";
        static::$testOutput = "\n\033[32m [✓] $featureAnnotation\033[0m\n";
    }

    /**
     * Prepares the test output string in case a test was not successfull.
     *
     * @param  Exception $e Test failure.
     *
     * @return void
     */
    protected function onNotSuccessfulTest(Exception $e)
    {
        static::$testOutput = str_replace('[32m [✓]', '[31m [×]', static::$testOutput);

        throw $e;
    }

    /**
     * Prints out the feature annotation (if present) with the status of the
     * given test class.
     *
     * @return void
     */
    public static function tearDownAfterClass()
    {
        echo static::$testOutput;
    }
}
