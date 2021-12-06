<?php

namespace Oro\Bundle\AttachmentBundle\Tests\Unit\Provider;

use Oro\Bundle\AttachmentBundle\Entity\File;
use Oro\Bundle\AttachmentBundle\Provider\FileNameProviderInterface;
use Oro\Bundle\AttachmentBundle\Provider\FileUrlProvider;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class FileUrlProviderTest extends \PHPUnit\Framework\TestCase
{
    private UrlGeneratorInterface|\PHPUnit\Framework\MockObject\MockObject $urlGenerator;

    private FileNameProviderInterface|\PHPUnit\Framework\MockObject\MockObject $filenameProvider;

    private FileUrlProvider $provider;

    protected function setUp(): void
    {
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->filenameProvider = $this->createMock(FileNameProviderInterface::class);
        $this->provider = new FileUrlProvider($this->urlGenerator, $this->filenameProvider);
    }

    public function testGetFileUrl(): void
    {
        $this->urlGenerator
            ->method('generate')
            ->with(
                'oro_attachment_get_file',
                [
                    'id' => $fileId = 1,
                    'filename' => $filename = 'sample-filename',
                    'action' => $action = 'sample-action',
                ],
                $referenceType = 1
            )
            ->willReturn($url = 'sample-url');

        self::assertEquals(
            $url,
            $this->provider->getFileUrl($this->getFile($fileId, $filename), $action, $referenceType)
        );
    }

    public function testGetResizedImageUrl(): void
    {
        $fileId = 1;
        $filename = 'sample-filename';
        $format = 'sample_format';
        $file = $this->getFile($fileId, $filename);

        $this->filenameProvider->expects(self::once())
            ->method('getFileName')
            ->with($file, $format)
            ->willReturn($filename);

        $this->urlGenerator
            ->method('generate')
            ->with(
                'oro_resize_attachment',
                [
                    'id' => $fileId,
                    'filename' => $filename,
                    'width' => $width = 10,
                    'height' => $height = 20,
                ],
                $referenceType = 1
            )
            ->willReturn($url = 'sample-url');

        self::assertEquals(
            $url,
            $this->provider->getResizedImageUrl($file, $width, $height, $format, $referenceType)
        );
    }

    public function testGetFilteredImageUrl(): void
    {
        $fileId = 1;
        $filename = 'sample-filename';
        $filter = 'sample-filter';
        $format = 'sample_format';
        $file = $this->getFile($fileId, $filename);

        $this->filenameProvider->expects(self::once())
            ->method('getFileName')
            ->with($file, $format)
            ->willReturn($filename);

        $this->urlGenerator
            ->method('generate')
            ->with(
                'oro_filtered_attachment',
                [
                    'id' => $fileId,
                    'filename' => $filename,
                    'filter' => $filter,
                ],
                $referenceType = 1
            )
            ->willReturn($url = 'sample-url');

        self::assertEquals(
            $url,
            $this->provider->getFilteredImageUrl($file, $filter, $format, $referenceType)
        );
    }

    private function getFile(int $id = null, string $filename = ''): File
    {
        $file = $this->createMock(File::class);
        $file
            ->method('getId')
            ->willReturn($id);

        $file
            ->method('getFilename')
            ->willReturn($filename);

        return $file;
    }
}
