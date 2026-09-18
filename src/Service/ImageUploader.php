<?php
// src/Service/ImageUploader.php

namespace App\Service;

use App\Entity\Card;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class ImageUploader
{
    private string $uploadDirectory;
    private SluggerInterface $slugger;

    public function __construct(string $uploadDirectory, SluggerInterface $slugger)
    {
        $this->uploadDirectory = $uploadDirectory;
        $this->slugger = $slugger;
    }

    /**
     * Загрузить изображение для карты
     */
    public function upload(Card $card, UploadedFile $file): ?string
    {
        // Генерируем пути для файла
        $paths = $this->generatePaths($card, $file);

        try {
            // Пытаемся сохранить как WebP
            $this->saveAsWebP($file, $paths['fullPath']);

            // Удаляем старый файл
            $this->removeOldFile($card, $paths['fullPath']);

            return $paths['relativePath'];

        } catch (\Exception $e) {
            // В случае ошибки сохраняем в оригинальном формате
            $this->saveOriginal($file, $paths['directory'], $paths['cardSlug']);

            // Удаляем старый файл
            $this->removeOldFile($card, $paths['fullPath']);

            return $paths['relativePath'];
        }
    }

    /**
     * Генерирует пути для файла
     */
    private function generatePaths(Card $card, UploadedFile $file): array
    {
        $raceName = $card->getRace() ? $card->getRace()->getName() : 'default';
        $raceSlug = $this->slugger->slug($raceName)->lower()->toString();

        $cardName = $card->getName();
        $cardSlug = $this->slugger->slug($cardName)->lower()->toString();

        if (empty($cardSlug)) {
            $cardSlug = 'card-' . uniqid();
        }

        $relativePath = $raceSlug . '/' . $cardSlug . '.webp';
        $fullPath = $this->uploadDirectory . '/' . $relativePath;
        $directory = dirname($fullPath);

        return [
            'raceSlug' => $raceSlug,
            'cardSlug' => $cardSlug,
            'relativePath' => $relativePath,
            'fullPath' => $fullPath,
            'directory' => $directory,
            'extension' => $file->guessExtension() ?? 'jpg'
        ];
    }

    /**
     * Сохраняет изображение в формате WebP
     */
    private function saveAsWebP(UploadedFile $file, string $fullPath): void
    {
        // Создаем директорию если её нет
        $directory = dirname($fullPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $image = $this->createImageFromFile($file);

        if ($image === false) {
            throw new \Exception('Не удалось создать изображение');
        }

        imagewebp($image, $fullPath, 85);
        imagedestroy($image);
    }

    /**
     * Сохраняет файл в оригинальном формате
     */
    private function saveOriginal(UploadedFile $file, string $directory, string $cardSlug): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $extension = $file->guessExtension() ?? 'jpg';
        $filename = $cardSlug . '.' . $extension;

        $file->move($directory, $filename);
    }

    /**
     * Создает ресурс изображения из файла
     */
    private function createImageFromFile(UploadedFile $file)
    {
        $mimeType = $file->getMimeType();

        switch ($mimeType) {
            case 'image/jpeg':
            case 'image/jpg':
                return imagecreatefromjpeg($file->getPathname());
            case 'image/png':
                $image = imagecreatefrompng($file->getPathname());
                imagepalettetotruecolor($image);
                imagealphablending($image, true);
                imagesavealpha($image, true);
                return $image;
            case 'image/webp':
                return imagecreatefromwebp($file->getPathname());
            case 'image/gif':
                return imagecreatefromgif($file->getPathname());
            default:
                return false;
        }
    }

    /**
     * Удаляет старый файл если он существует и отличается от нового
     */
    private function removeOldFile(Card $card, string $newFullPath): void
    {
        if (!$card->getImagePath()) {
            return;
        }

        $oldFile = $this->uploadDirectory . '/' . $card->getImagePath();

        if (file_exists($oldFile) && $oldFile !== $newFullPath) {
            unlink($oldFile);
        }
    }

    /**
     * Удалить изображение
     */
    public function remove(?string $imagePath): bool
    {
        if (!$imagePath) {
            return false;
        }

        $fullPath = $this->uploadDirectory . '/' . $imagePath;

        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }

        return false;
    }

    /**
     * Получить путь к директории загрузки
     */
    public function getUploadDirectory(): string
    {
        return $this->uploadDirectory;
    }
}