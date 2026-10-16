<?php

namespace App\Http\Controllers\Dokter;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class JadwalPeriksaController extends Controller
{
    private const HARI = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

    public function index()
    {
        $jadwals = Auth::user()->jadwalPeriksas()
            ->orderByRaw("FIELD(hari, 'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu')")
            ->orderBy('jam_mulai')
            ->get();

        return view('dokter.jadwal-periksa.index', compact('jadwals'));
    }

    public function create()
    {
        return view('dokter.jadwal-periksa.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'hari'        => ['required', Rule::in(self::HARI)],
            'jam_mulai'   => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i|after:jam_mulai',
        ]);

        if ($this->bentrok($validated['hari'], $validated['jam_mulai'], $validated['jam_selesai'])) {
            return back()->withInput()
                ->withErrors(['jam_mulai' => 'Jadwal bentrok dengan jadwal lain di hari yang sama.']);
        }

        // status otomatis "Tidak Aktif" (default di database)
        Auth::user()->jadwalPeriksas()->create($validated);

        return redirect()->route('jadwal-periksa.index')
            ->with('success', 'Jadwal periksa berhasil ditambahkan');
    }

    public function edit(string $id)
    {
        $jadwal = Auth::user()->jadwalPeriksas()->findOrFail($id);
        return view('dokter.jadwal-periksa.edit', compact('jadwal'));
    }

    public function update(Request $request, string $id)
    {
        $jadwal = Auth::user()->jadwalPeriksas()->findOrFail($id);

        $validated = $request->validate([
            'hari'        => ['required', Rule::in(self::HARI)],
            'jam_mulai'   => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i|after:jam_mulai',
            'status'      => ['required', Rule::in(['Aktif', 'Tidak Aktif'])],
        ]);

        if ($this->bentrok($validated['hari'], $validated['jam_mulai'], $validated['jam_selesai'], $jadwal->id)) {
            return back()->withInput()
                ->withErrors(['jam_mulai' => 'Jadwal bentrok dengan jadwal lain di hari yang sama.']);
        }

        // Hanya boleh satu jadwal aktif per dokter
        if ($validated['status'] === 'Aktif') {
            Auth::user()->jadwalPeriksas()
                ->where('id', '!=', $jadwal->id)
                ->update(['status' => 'Tidak Aktif']);
        }

        $jadwal->update($validated);

        return redirect()->route('jadwal-periksa.index')
            ->with('success', 'Jadwal periksa berhasil diperbarui');
    }

    public function destroy(string $id)
    {
        $jadwal = Auth::user()->jadwalPeriksas()->findOrFail($id);
        $jadwal->delete();

        return redirect()->route('jadwal-periksa.index')
            ->with('success', 'Jadwal periksa berhasil dihapus');
    }

    /**
     * Cek apakah jam bertabrakan dengan jadwal dokter lain di hari yang sama.
     */
    private function bentrok(string $hari, string $mulai, string $selesai, ?int $ignoreId = null): bool
    {
        return Auth::user()->jadwalPeriksas()
            ->where('hari', $hari)
            ->where('jam_mulai', '<', $selesai)
            ->where('jam_selesai', '>', $mulai)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }
}