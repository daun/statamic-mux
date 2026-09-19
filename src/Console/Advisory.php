<?php

namespace Daun\StatamicMux\Console;

final readonly class Advisory
{
    /**
     * @param  list<string>  $records  Affected record identifiers, rendered uncapped
     */
    public function __construct(
        public AdvisoryLevel $level,
        public string $code,
        public string $message,
        public array $records = [],
        public ?string $hint = null,
    ) {}

    /**
     * @param  list<string>  $records
     */
    public static function info(string $code, string $message, array $records = [], ?string $hint = null): self
    {
        return new self(AdvisoryLevel::Info, $code, $message, $records, $hint);
    }

    /**
     * @param  list<string>  $records
     */
    public static function warn(string $code, string $message, array $records = [], ?string $hint = null): self
    {
        return new self(AdvisoryLevel::Warn, $code, $message, $records, $hint);
    }

    /**
     * @param  list<string>  $records
     */
    public static function error(string $code, string $message, array $records = [], ?string $hint = null): self
    {
        return new self(AdvisoryLevel::Error, $code, $message, $records, $hint);
    }

    /**
     * @return array{level: string, code: string, records: list<string>}
     */
    public function toArray(): array
    {
        return [
            'level' => $this->level->value,
            'code' => $this->code,
            'records' => $this->records,
        ];
    }
}
