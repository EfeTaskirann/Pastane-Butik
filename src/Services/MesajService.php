<?php

declare(strict_types=1);

namespace Pastane\Services;

use Pastane\Repositories\MesajRepository;
use Pastane\Exceptions\ValidationException;

/**
 * Mesaj Service
 *
 * Mesaj business logic.
 *
 * @package Pastane\Services
 * @since 1.0.0
 */
class MesajService extends BaseService
{
    /**
     * @var MesajRepository
     */
    protected MesajRepository $mesajRepository;

    /**
     * Constructor
     *
     * @param MesajRepository|null $repository
     */
    public function __construct(?MesajRepository $repository = null)
    {
        $this->mesajRepository = $repository ?? new MesajRepository();
        $this->repository = $this->mesajRepository;
    }

    /**
     * Tüm mesajları sıralı getir
     * Önce okunmamışlar, sonra tarihe göre
     *
     * @return array
     */
    public function getAllOrdered(): array
    {
        return $this->mesajRepository->getAllOrdered();
    }

    /**
     * Okunmamış mesajları getir
     *
     * @return array
     */
    public function getUnread(): array
    {
        return $this->mesajRepository->getUnread();
    }

    /**
     * Okunmamış mesaj sayısını getir
     *
     * @return int
     */
    public function getUnreadCount(): int
    {
        return $this->mesajRepository->getUnreadCount();
    }

    /**
     * Mesajı okundu olarak işaretle
     *
     * @param int $id
     * @return bool
     */
    public function markAsRead(int $id): bool
    {
        // Mesaj var mı kontrol et
        $this->repository->findOrFail($id);
        return $this->mesajRepository->markAsRead($id);
    }

    /**
     * Tüm mesajları okundu olarak işaretle
     *
     * @return int Güncellenen satır sayısı
     */
    public function markAllAsRead(): int
    {
        return $this->mesajRepository->markAllAsRead();
    }

    /**
     * Validate before create
     *
     * @param array $data
     * @throws ValidationException
     */
    protected function validateCreate(array $data): void
    {
        $this->validate($data, [
            'ad' => 'required|string|min:2|max:100',
            'mesaj' => 'required|string|min:10',
        ]);

        // Email varsa geçerli olmalı
        if (!empty($data['email'])) {
            $this->validate($data, [
                'email' => 'email',
            ]);
        }
    }
}
