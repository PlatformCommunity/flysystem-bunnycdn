<?php

use League\Flysystem\Config;
use League\Flysystem\Rackspace\RackspaceAdapter as Rackspace;

class RackspaceTests extends PHPUnit_Framework_TestCase
{
    public function getContainerMock()
    {
        return Mockery::mock('OpenCloud\ObjectStore\Resource\Container');
    }

    public function getDataObjectMock($filename)
    {
        $mock = Mockery::mock('OpenCloud\ObjectStore\Resource\DataObject');
        $mock->shouldReceive('getName')->andReturn($filename);
        $mock->shouldReceive('getContentType')->andReturn('; plain/text');
        $mock->shouldReceive('getLastModified')->andReturn('2014-01-01');
        $mock->shouldReceive('getContentLength')->andReturn(4);

        return $mock;
    }

    public function testRead()
    {
        $container = $this->getContainerMock();
        $dataObject = $this->getDataObjectMock('filename.ext');
        $dataObject->shouldReceive('getContent')->andReturn('file contents');
        $container->shouldReceive('getObject')->andReturn($dataObject);
        $adapter = new Rackspace($container);
        $this->assertInternalType('array', $adapter->read('filename.ext'));
    }

    public function testReadStream()
    {
        $resource = tmpfile();
        $container = $this->getContainerMock();
        $dataObject = $this->getDataObjectMock('some%20directory/filename.ext');
        $body = Mockery::mock('Guzzle\Http\EntityBody');
        $body->shouldReceive('close');
        $body->shouldReceive('rewind');
        $body->shouldReceive('getStream')->andReturn($resource);
        $body->shouldReceive('detachStream');
        $dataObject->shouldReceive('getContent')->andReturn($body);
        $container->shouldReceive('getObject')->with('some%20directory/filename.ext')->andReturn($dataObject);
        $adapter = new Rackspace($container);
        $response = $adapter->readStream('some directory/filename.ext');
        $this->assertInternalType('array', $response);
        $this->assertEquals($resource, $response['stream']);
        fclose($resource);
    }

    public function testPrefixed()
    {
        $container = $this->getContainerMock();
        $dataObject = $this->getDataObjectMock('prefix/filename.ext');
        $dataObject->shouldReceive('getContent')->andReturn('file contents');
        $container->shouldReceive('getObject')->andReturn($dataObject);
        $adapter = new Rackspace($container, 'prefix');
        $this->assertInternalType('array', $adapter->read('filename.ext'));
    }

    public function testHas()
    {
        $container = $this->getContainerMock();
        $container->shouldReceive('objectExists')->andReturn(true);
        $adapter = new Rackspace($container);
        $this->assertTrue($adapter->has('filename.ext'));
    }

    public function testHasFail()
    {
        $container = $this->getContainerMock();
        $container->shouldReceive('objectExists')->andThrow('Guzzle\Http\Exception\ClientErrorResponseException');
        $adapter = new Rackspace($container);
        $this->assertFalse($adapter->has('filename.ext'));
    }

    public function testHasNotFound()
    {
        $container = $this->getContainerMock();
        $container->shouldReceive('objectExists')->andReturn(false);
        $adapter = new Rackspace($container);
        $this->assertFalse($adapter->has('filename.ext'));
    }

    public function testWrite()
    {
        $container = $this->getContainerMock();
        $dataObject = $this->getDataObjectMock('filename.ext');
        $container->shouldReceive('uploadObject')->with('filename.ext', 'content', [])->andReturn($dataObject);
        $adapter = new Rackspace($container);
        $this->assertInternalType('array', $adapter->write('filename.ext', 'content', new Config()));
    }

    public function testWriteWithHeaders()
    {
        $container = $this->getContainerMock();
        $dataObject = $this->getDataObjectMock('filename.ext');
        $headers = ['custom' => 'headers'];
        $container->shouldReceive('uploadObject')->with('filename.ext', 'content', $headers)->andReturn($dataObject);
        $adapter = new Rackspace($container);
        $config = new Config(['headers' => $headers]);
        $this->assertInternalType('array', $adapter->write('filename.ext', 'content', $config));
    }

    public function testWriteStream()
    {
        $container = $this->getContainerMock();
        $dataObject = $this->getDataObjectMock('filename.ext');
        $container->shouldReceive('uploadObject')->andReturn($dataObject);
        $adapter = new Rackspace($container);
        $config = new Config([]);
        $stream = tmpfile();
        fwrite($stream, 'something');
        $this->assertInternalType('array', $adapter->writeStream('filename.ext', $stream, $config));
        fclose($stream);
    }

    public function testUpdateFail()
    {
        $container = $this->getContainerMock();
        $dataObject = Mockery::mock('OpenCloud\ObjectStore\Resource\DataObject');
        $dataObject->shouldReceive('getLastModified')->andReturn(false);
        $dataObject->shouldReceive('setContent');
        $dataObject->shouldReceive('setEtag');
        $dataObject->shouldReceive('update')->andReturn(Mockery::self());
        $container->shouldReceive('getObject')->andReturn($dataObject);
        $adapter = new Rackspace($container);
        $this->assertFalse($adapter->update('filename.ext', 'content', new Config()));
    }

    public function testUpdate()
    {
        $container = $this->getContainerMock();
        $dataObject = $this->getDataObjectMock('filename.ext');
        $dataObject->shouldReceive('setContent');
        $dataObject->shouldReceive('setEtag');
        $dataObject->shouldReceive('update')->andReturn(Mockery::self());
        $container->shouldReceive('getObject')->andReturn($dataObject);
        $adapter = new Rackspace($container);
        $this->assertInternalType('array', $adapter->update('filename.ext', 'content', new Config()));
    }

    public function testUpdateStream()
    {
        $container = $this->getContainerMock();
        $dataObject = $this->getDataObjectMock('filename.ext');
        $dataObject->shouldReceive('setContent');
        $dataObject->shouldReceive('setEtag');
        $dataObject->shouldReceive('update')->andReturn(Mockery::self());
        $container->shouldReceive('getObject')->andReturn($dataObject);
        $adapter = new Rackspace($container);
        $resource = tmpfile();
        $this->assertInternalType('array', $adapter->updateStream('filename.ext', $resource, new Config()));
        fclose($resource);
    }

    public function testCreateDir()
    {
        $container = $this->getContainerMock();
        $dataObject = $this->getDataObjectMock('dirname');
        $container->shouldReceive('uploadObject')->with(
            'dirname',
            '',
            ['Content-Type' => 'application/directory']
        )->andReturn($dataObject);

        $adapter = new Rackspace($container);
        $response = $adapter->createDir('dirname', new Config());
        $this->assertInternalType('array', $response);
        $this->assertEquals('dirname', $response['path']);
    }

    public function getterProvider()
    {
        return [
            ['getTimestamp'],
            ['getSize'],
            ['getMimetype'],
        ];
    }

    /**
     * @dataProvider  getterProvider
     */
    public function testGetters($function)
    {
        $container = $this->getContainerMock();
        $dataObject = $this->getDataObjectMock('filename.ext');
        $container->shouldReceive('getPartialObject')->andReturn($dataObject);
        $adapter = new Rackspace($container);
        $this->assertInternalType('array', $adapter->{$function}('filename.ext'));
    }

    public function testDelete()
    {
        $container = $this->getContainerMock();
        $container->shouldReceive('deleteObject');
        $adapter = new Rackspace($container);
        $this->assertTrue($adapter->delete('filename.ext'));
    }

    public function testDeleteNotFound()
    {
        $container = $this->getContainerMock();
        $container->shouldReceive('deleteObject')->andThrow('Guzzle\Http\Exception\BadResponseException');
        $adapter = new Rackspace($container);
        $this->assertFalse($adapter->delete('filename.txt'));
    }

    public function renameProvider()
    {
        return [
            [201, true],
            [500, false],
        ];
    }

    /**
     * @dataProvider  renameProvider
     */
    public function testRename($status, $expected)
    {
        $container = $this->getContainerMock();
        $container->shouldReceive('getName')->andReturn('container_name');
        $dataObject = Mockery::mock('OpenCloud\ObjectStore\Resource\DataObject');
        $dataObject->shouldReceive('copy')->andReturn(Mockery::self());
        $dataObject->shouldReceive('getStatusCode')->andReturn($status);
        $container->shouldReceive('getObject')->andReturn($dataObject);

        if ($expected) {
            $dataObject->shouldReceive('delete');
        }

        $adapter = new Rackspace($container);
        $this->assertEquals($expected, $adapter->rename('filename.ext', 'other.ext'));
    }

    public function deleteDirProvider()
    {
        return [
            [200, true],
            [500, false],
        ];
    }

    /**
     * @dataProvider  deleteDirProvider
     */
    public function testDeleteDir($status, $expected)
    {
        $container = $this->getContainerMock();
        $container->shouldReceive('getName')->andReturn('container_name');
        $dataObject = Mockery::mock('OpenCloud\ObjectStore\Resource\DataObject');
        $dataObject->shouldReceive('getName')->andReturn('filename.ext');
        $container->shouldReceive('objectList')->andReturn([$dataObject]);
        $container->shouldReceive('getService')->andReturn($container);
        $container->shouldReceive('bulkDelete')->andReturn($container);
        $container->shouldReceive('getStatusCode')->andReturn($status);
        $adapter = new Rackspace($container);
        $this->assertEquals($expected, $adapter->deleteDir(''));
    }

    public function testListContents()
    {
        $container = $this->getContainerMock();
        $container->shouldReceive('getName')->andReturn('container_name');
        $dataObject = $this->getDataObjectMock('filename.ext');
        $container->shouldReceive('objectList')->andReturn(new ArrayIterator([$dataObject]), new ArrayIterator());
        $adapter = new Rackspace($container);
        $this->assertInternalType('array', $adapter->listContents('', true));
    }

    public function testGetContainer()
    {
        $container = $this->getContainerMock();
        $adapter = new Rackspace($container);

        $this->assertEquals($container, $adapter->getContainer());
    }
}
