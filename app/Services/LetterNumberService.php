<?php

namespace App\Services;

use App\Models\Letter;
use App\Models\LetterSubCategory;

class LetterNumberService
{
    public static function generate($subCategoryId)
    {
        $sub = LetterSubCategory::with('category')->findOrFail($subCategoryId);
        $year = date('Y');
        $month = self::toRoman(date('m'));

        // Ambil nomor urut tertinggi pada kategori utama di tahun berjalan
        $lastSequence = Letter::whereHas('subCategory', function ($q) use ($sub) {
                $q->where('letter_category_id', $sub->letter_category_id);
            })
            ->whereYear('letter_date', $year)
            ->max('sequence_number');

        $newSequence = ($lastSequence ?? 0) + 1;
        $paddedNumber = str_pad($newSequence, 3, '0', STR_PAD_LEFT);

        // Merakit nomor berdasarkan template
        $fullNumber = str_replace(
            ['{code}', '{sub}', '{no}', '{month}', '{year}'],
            [$sub->category->code, $sub->sub_code, $paddedNumber, $month, $year],
            $sub->category->format_template
        );

        return [
            'full_number' => $fullNumber,
            'sequence' => $newSequence
        ];
    }

    private static function toRoman($month)
    {
        $map = [1=>'I', 2=>'II', 3=>'III', 4=>'IV', 5=>'V', 6=>'VI', 7=>'VII', 8=>'VIII', 9=>'IX', 10=>'X', 11=>'XI', 12=>'XII'];
        return $map[(int)$month] ?? $month;
    }
}