{{ $livewireKey }}.{{
                    substr(md5(serialize([
                        $disabledDates,
                        $isDisabled,
                        $isReadOnly,
                        $maxDate,
                        $minDate,
                        $hasDate,
                        $hasTime,
                        $hasSeconds,
                    ])), 0, 64)
                }}