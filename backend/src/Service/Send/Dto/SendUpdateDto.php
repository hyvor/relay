<?php

namespace App\Service\Send\Dto;

use App\Entity\Type\SendStatus;

class SendUpdateDto
{


    public SendStatus $status {
        set {
            $this->statusSet = true;
            $this->status = $value;
        }
    }

    public \DateTimeImmutable $sentAt {
        set {
            $this->sentAtSet = true;
            $this->sentAt = $value;
        }
    }

    public \DateTimeImmutable $failedAt {
        set {
            $this->failedAtSet = true;
            $this->failedAt = $value;
        }
    }

    public string $result {
        set {
            $this->result = $value;
            $this->resultSet = true;
        }
    }

    private(set) bool $statusSet = false;
    private(set) bool $sentAtSet = false;
    private(set) bool $failedAtSet = false;
    private(set) bool $resultSet = false;

}