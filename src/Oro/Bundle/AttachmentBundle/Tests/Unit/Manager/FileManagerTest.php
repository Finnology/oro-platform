<?php

namespace Oro\Bundle\AttachmentBundle\Tests\Unit\Manager;

use Gaufrette\Adapter\GridFS;
use Gaufrette\Exception\FileNotFound;
use Gaufrette\Filesystem;
use Gaufrette\Stream\InMemoryBuffer;
use Gaufrette\StreamMode;
use Knp\Bundle\GaufretteBundle\FilesystemMap;
use Oro\Bundle\AttachmentBundle\Manager\FileManager;
use Oro\Bundle\AttachmentBundle\Tests\Unit\Fixtures\TestFile;
use Oro\Bundle\AttachmentBundle\Validator\ProtocolValidatorInterface;
use Oro\Bundle\SecurityBundle\Tools\UUIDGenerator;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class FileManagerTest extends \PHPUnit\Framework\TestCase
{
    /** @var  \PHPUnit\Framework\MockObject\MockObject */
    protected $filesystem;

    /** @var  \PHPUnit\Framework\MockObject\MockObject */
    protected $protocolValidator;

    /** @var FileManager */
    protected $fileManager;

    protected function setUp()
    {
        $this->filesystem = $this->createMock(Filesystem::class);

        $filesystemMap = $this->createMock(FilesystemMap::class);
        $filesystemMap->expects($this->once())
            ->method('get')
            ->with('attachments')
            ->willReturn($this->filesystem);

        $this->protocolValidator = $this->createMock(ProtocolValidatorInterface::class);

        $this->fileManager = new FileManager('attachments', $this->protocolValidator);
        $this->fileManager->setFilesystemMap($filesystemMap);
    }

    /**
     * @param string|null $originalFileName
     * @param string|null $fileName
     *
     * @return TestFile
     */
    protected function createFileEntity($originalFileName = 'testFile.txt', $fileName = 'testFile.txt')
    {
        $fileEntity = new TestFile();
        if (null !== $originalFileName) {
            $fileEntity->setOriginalFilename($originalFileName);
        }
        if (null !== $fileName) {
            $fileEntity->setFilename($fileName);
        }

        return $fileEntity;
    }

    public function testGetContentByFileEntity()
    {
        $fileEntity = $this->createFileEntity();
        $fileContent = 'test data';

        $file = $this->createMock(\Gaufrette\File::class);
        $file->expects($this->once())
            ->method('getContent')
            ->willReturn($fileContent);

        $this->filesystem->expects($this->never())
            ->method('has');
        $this->filesystem->expects($this->once())
            ->method('get')
            ->with($fileEntity->getFilename())
            ->willReturn($file);

        $this->assertEquals($fileContent, $this->fileManager->getContent($fileEntity));
    }

    /**
     * @expectedException \Gaufrette\Exception\FileNotFound
     */
    public function testGetContentWhenFileDoesNotExist()
    {
        $fileName = 'testFile.txt';

        $this->filesystem->expects($this->never())
            ->method('has');
        $this->filesystem->expects($this->once())
            ->method('get')
            ->with($fileName)
            ->willThrowException(new FileNotFound($fileName));

        $this->fileManager->getContent($fileName);
    }

    public function testCreateFileEntity()
    {
        $path = __DIR__ . '/../Fixtures/testFile/test.txt';

        $this->protocolValidator->expects($this->never())
            ->method('isSupportedProtocol');

        $result = $this->fileManager->createFileEntity($path);
        $this->assertEquals('test.txt', $result->getOriginalFilename());
        $this->assertFileEquals($path, $result->getFile()->getPathname());
    }

    public function testSetFileFromPath(): void
    {
        $path = __DIR__ . '/../Fixtures/testFile/test.txt';

        $this->protocolValidator->expects($this->never())
            ->method('isSupportedProtocol');

        $file = $this->createFileEntity();
        $this->fileManager->setFileFromPath($file, $path);
        $this->assertEquals('test.txt', $file->getOriginalFilename());
        $this->assertFileEquals($path, $file->getFile()->getPathname());
    }

    /**
     * @dataProvider fileWithoutProtocolDataProvider
     * @expectedException \Symfony\Component\Filesystem\Exception\FileNotFoundException
     */
    public function testCreateFileEntityWhenProtocolIsNotSpecified($path)
    {
        $this->protocolValidator->expects($this->never())
            ->method('isSupportedProtocol');

        $this->fileManager->createFileEntity($path);
    }

    public function fileWithoutProtocolDataProvider()
    {
        return [
            [''],
            [' '],
            ['/file.txt'],
            ['\\server\file.txt'],
            ['C:\file.txt'],
            ['c:/file.txt']
        ];
    }

    /**
     * @dataProvider fileWithoutProtocolDataProvider
     * @expectedException \Symfony\Component\Filesystem\Exception\FileNotFoundException
     *
     * @param string $path
     */
    public function testSetFileFromPathWhenProtocolIsNotSpecified(string $path): void
    {
        $this->protocolValidator->expects($this->never())
            ->method('isSupportedProtocol');

        $this->fileManager->setFileFromPath($this->createFileEntity(), $path);
    }

    /**
     * @dataProvider supportedFileProtocolDataProvider
     * @expectedException \Symfony\Component\Filesystem\Exception\FileNotFoundException
     */
    public function testCreateFileEntityWhenProtocolIsSupported($path, $expectedProtocol)
    {
        $this->protocolValidator->expects($this->once())
            ->method('isSupportedProtocol')
            ->with($expectedProtocol)
            ->willReturn(true);

        $this->fileManager->createFileEntity($path);
    }

    public function supportedFileProtocolDataProvider()
    {
        return [
            ['file://file.txt', 'file'],
            ['File://file.txt', 'file'],
            [' FILE://file.txt ', 'file']
        ];
    }

    /**
     * @dataProvider supportedFileProtocolDataProvider
     * @expectedException \Symfony\Component\Filesystem\Exception\FileNotFoundException
     *
     * @param string $path
     * @param string $expectedProtocol
     */
    public function testSetFileFromPathWhenProtocolIsSupported(string $path, string $expectedProtocol): void
    {
        $this->protocolValidator->expects($this->once())
            ->method('isSupportedProtocol')
            ->with($expectedProtocol)
            ->willReturn(true);

        $this->fileManager->setFileFromPath($this->createFileEntity(), $path);
    }

    /**
     * @dataProvider notSupportedFileProtocolDataProvider
     * @expectedException \Oro\Bundle\AttachmentBundle\Exception\ProtocolNotSupportedException
     */
    public function testCreateFileEntityWhenProtocolIsNotSupported($path, $expectedProtocol)
    {
        $this->protocolValidator->expects($this->once())
            ->method('isSupportedProtocol')
            ->with($expectedProtocol)
            ->willReturn(false);

        $this->fileManager->createFileEntity($path);
    }

    public function notSupportedFileProtocolDataProvider()
    {
        return [
            ['phar://test.phar/file.txt', 'phar'],
            ['Phar://test.phar/file.txt', 'phar'],
            [' PHAR://test.phar/file.txt ', 'phar']
        ];
    }

    /**
     * @dataProvider notSupportedFileProtocolDataProvider
     * @expectedException \Oro\Bundle\AttachmentBundle\Exception\ProtocolNotSupportedException
     *
     * @param string $path
     * @param string $expectedProtocol
     */
    public function testSetFileFromPathWhenProtocolIsNotSupported(string $path, string $expectedProtocol): void
    {
        $this->protocolValidator->expects($this->once())
            ->method('isSupportedProtocol')
            ->with($expectedProtocol)
            ->willReturn(false);

        $this->fileManager->setFileFromPath($this->createFileEntity(), $path);
    }

    /**
     * @expectedException \Symfony\Component\Filesystem\Exception\FileNotFoundException
     */
    public function testCreateFileEntityForNotExistingFile()
    {
        $path = __DIR__ . '/../Fixtures/testFile/not_existed.txt';

        $this->fileManager->createFileEntity($path);
    }

    public function testCloneFileEntity()
    {
        $fileEntity = $this->createFileEntity();

        $file = $this->createMock(\Gaufrette\File::class);
        $fileContent = 'test';

        $this->filesystem->expects($this->once())
            ->method('has')
            ->with($fileEntity->getFilename())
            ->willReturn(true);
        $this->filesystem->expects($this->once())
            ->method('get')
            ->with($fileEntity->getFilename())
            ->willReturn($file);
        $file->expects($this->once())
            ->method('getContent')
            ->willReturn($fileContent);

        $clonedFileEntity = $this->fileManager->cloneFileEntity($fileEntity);

        $this->assertNotSame($fileEntity, $clonedFileEntity);
        $this->assertEquals($fileEntity->getOriginalFilename(), $clonedFileEntity->getOriginalFilename());
        $this->assertNull($clonedFileEntity->getFilename());
        $this->assertNotNull($clonedFileEntity->getFile());
        $this->assertEquals(
            $fileContent,
            file_get_contents($clonedFileEntity->getFile()->getRealPath())
        );
    }

    public function testCloneFileEntityWhenFileDoesNotExist()
    {
        $fileEntity = $this->createFileEntity();

        $this->filesystem->expects($this->once())
            ->method('has')
            ->with($fileEntity->getFilename())
            ->willReturn(false);
        $this->filesystem->expects($this->never())
            ->method('get');

        $clonedFileEntity = $this->fileManager->cloneFileEntity($fileEntity);

        $this->assertNull($clonedFileEntity);
    }

    public function testGetFileFromFileEntity(): void
    {
        $fileEntity = $this->createFileEntity();

        $file = $this->createMock(\Gaufrette\File::class);
        $fileContent = 'test';

        $this->filesystem->expects($this->once())
            ->method('has')
            ->with($fileEntity->getFilename())
            ->willReturn(true);
        $this->filesystem->expects($this->once())
            ->method('get')
            ->with($fileEntity->getFilename())
            ->willReturn($file);
        $file->expects($this->once())
            ->method('getContent')
            ->willReturn($fileContent);

        $symfonyFile = $this->fileManager->getFileFromFileEntity($fileEntity, false);

        $this->assertNotNull($symfonyFile);
        $this->assertEquals(
            $fileContent,
            file_get_contents($symfonyFile->getRealPath())
        );
    }

    public function testGetFileFromFileEntityWhenFileDoesNotExist(): void
    {
        $fileEntity = $this->createFileEntity();

        $this->filesystem->expects($this->once())
            ->method('has')
            ->with($fileEntity->getFilename())
            ->willReturn(false);
        $this->filesystem->expects($this->never())
            ->method('get');

        $this->assertNull($this->fileManager->getFileFromFileEntity($fileEntity, false));
    }

    public function testGetFileFromFileEntityWhenFileDoesNotExistAndException(): void
    {
        $fileEntity = $this->createFileEntity();

        $this->filesystem->expects($this->never())
            ->method('has');
        $this->filesystem->expects($this->once())
            ->method('get')
            ->willThrowException(new FileNotFoundException());

        $this->expectException(FileNotFoundException::class);

        $this->assertNull($this->fileManager->getFileFromFileEntity($fileEntity, true));
    }

    public function testPreUploadDeleteFile()
    {
        $fileEntity = $this->createFileEntity();
        $fileEntity
            ->setUuid(UUIDGenerator::v4())
            ->setFilename('test.txt')
            ->setOriginalFilename('test-orig.txt')
            ->setEmptyFile(true)
            ->setExtension('txt')
            ->setFileSize(100)
            ->setMimeType('text/plain');

        $this->fileManager->preUpload($fileEntity);

        $this->assertNull($fileEntity->getOriginalFilename());
        $this->assertNull($fileEntity->getExtension());
        $this->assertNull($fileEntity->getMimeType());
        $this->assertNull($fileEntity->getFileSize());
        $this->assertEquals($fileEntity->getUuid(), $fileEntity->getFilename());
    }

    public function testPreUploadForUploadedFile()
    {
        $fileEntity = $this->createFileEntity();
        $file = new UploadedFile(__DIR__ . '/../Fixtures/testFile/test.txt', 'originalFile.csv', 'text/csv');
        $fileEntity
            ->setEmptyFile(false)
            ->setFile($file);

        $this->fileManager->preUpload($fileEntity);

        $this->assertEquals('originalFile.csv', $fileEntity->getOriginalFilename());
        $this->assertEquals('csv', $fileEntity->getExtension());
        $this->assertEquals('text/csv', $fileEntity->getMimeType());
        $this->assertEquals(9, $fileEntity->getFileSize());
        $this->assertNotEquals('testFile.txt', $fileEntity->getFilename());
    }

    public function testPreUploadForRegularFile()
    {
        $fileEntity = $this->createFileEntity();
        $file = new File(__DIR__ . '/../Fixtures/testFile/test.txt');
        $fileEntity
            ->setEmptyFile(false)
            ->setFile($file);

        $this->fileManager->preUpload($fileEntity);

        $this->assertEquals('testFile.txt', $fileEntity->getOriginalFilename());
        $this->assertEquals('txt', $fileEntity->getExtension());
        $this->assertEquals('text/plain', $fileEntity->getMimeType());
        $this->assertEquals(9, $fileEntity->getFileSize());
        $this->assertNotEquals('testFile.txt', $fileEntity->getFilename());
    }

    public function testUpload()
    {
        $fileEntity = $this->createFileEntity();
        $fileEntity->setEmptyFile(false);

        $file = new File(__DIR__ . '/../Fixtures/testFile/test.txt');
        $fileEntity->setFile($file);

        $memoryBuffer = new InMemoryBuffer($this->filesystem, 'test.txt');

        $this->filesystem->expects($this->once())
            ->method('createStream')
            ->with($fileEntity->getFilename())
            ->willReturn($memoryBuffer);

        $adapter = $this->createMock(GridFS::class);
        $this->filesystem->expects($this->any())
            ->method('getAdapter')
            ->willReturn($adapter);
        $adapter->expects($this->once())
            ->method('setMetadata')
            ->with(
                $fileEntity->getFilename(),
                ['contentType' => $fileEntity->getMimeType()]
            );

        $this->fileManager->upload($fileEntity);
        $memoryBuffer->open(new StreamMode('rb+'));
        $memoryBuffer->seek(0);

        $this->assertEquals('Test data', $memoryBuffer->read(100));
    }
}
