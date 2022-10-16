<?php

namespace App\Http\Controllers\masterData;

use App\Http\Controllers\Controller;
use App\Models\Kecamatan;
use App\Models\OrangTua;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\Facades\DataTables;

class OrangTuaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = OrangTua::with(['desa'])->orderBy('created_at', 'desc')->where(function ($query) use ($request) {
                if ($request->kecamatan_id && $request->kecamatan_id != "semua") {
                    $query->whereHas('desa', function ($query) use ($request) {
                        $query->whereHas('kecamatan', function ($query) use ($request) {
                            $query->where('id', $request->kecamatan_id);
                        });
                    });
                }

                if ($request->desa_id && $request->desa_id != "semua") {
                    $query->where('desa_id', $request->desa_id);
                }
            })->get();
            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('nama_ibu', function ($row) {
                    return $row->nama_ibu ?? '-';
                })
                ->addColumn('nik_ibu', function ($row) {
                    return $row->nik_ibu ?? '-';
                })
                ->addColumn('nama_ayah', function ($row) {
                    return $row->nama_ayah ?? '-';
                })
                ->addColumn('nik_ayah', function ($row) {
                    return $row->nik_ayah ?? '-';
                })
                ->addColumn('desa', function ($row) {
                    return $row->desa->nama;
                })
                ->addColumn('kecamatan', function ($row) {
                    return $row->desa->kecamatan->nama;
                })
                ->addColumn('rt', function ($row) {
                    return $row->rt ?? '-';
                })
                ->addColumn('rw', function ($row) {
                    return $row->rw ?? '-';
                })
                ->addColumn('action', function ($row) {
                    $actionBtn = '';

                    $actionBtn .= '<button class="btn btn-success btn-rounded btn-sm mr-1" id="btn-lihat" value="' . $row->id . '"><i class="far fa-eye"></i></button>';

                    if (Auth::user()->role == "Admin") {
                        $actionBtn .= '<a id="btn-edit" class="btn btn-warning btn-rounded btn-sm mr-1" href="' . url('master-data/orang-tua/' . $row->id . '/edit')  . '" ><i class="fas fa-edit"></i></a><button id="btn-delete" class="btn btn-danger btn-rounded btn-sm mr-1" value="' . $row->id . '" > <i class="fas fa-trash-alt"></i></button>';
                    }
                    return $actionBtn;
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        $daftarKecamatan = Kecamatan::orderBy('nama', 'asc')->get();
        // $daftarJumlahPenduduk = $this->_getJumlahPenduduk();

        return view('dashboard.pages.masterData.orangTua.index', compact(['daftarKecamatan']));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('dashboard.pages.masterData.orangTua.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $namaIbu = $request->nama_ibu;
        $nikIbu = $request->nik_ibu;
        $namaAyah = $request->nama_ayah;
        $nikAyah = $request->nik_ayah;

        if ((!$namaIbu && !$nikIbu) && (!$namaAyah && !$nikAyah)) {
            $validatorNamaIbu = 'required';
            $validatorNikIbu = ['required', Rule::unique('orang_tua')->withoutTrashed(), 'digits:16'];
            $validatorNamaAyah = 'required';
            $validatorNikAyah = ['required', Rule::unique('orang_tua')->withoutTrashed(), 'digits:16'];
        } else if ((($namaIbu && !$nikIbu) || (!$namaIbu && $nikIbu) || ($namaIbu && $nikIbu))  && (!$namaAyah && !$nikAyah)) {
            $validatorNamaIbu = 'required';
            $validatorNikIbu = ['required', Rule::unique('orang_tua')->withoutTrashed(), 'digits:16'];
            $validatorNamaAyah = 'nullable';
            $validatorNikAyah = 'nullable';
        } else if ((($namaAyah && !$nikAyah) || (!$namaAyah && $nikAyah) || ($namaAyah && $nikAyah))  && (!$namaIbu && !$nikIbu)) {
            $validatorNamaIbu = 'nullable';
            $validatorNikIbu = 'nullable';
            $validatorNamaAyah = 'required';
            $validatorNikAyah = ['required', Rule::unique('orang_tua')->withoutTrashed(), 'digits:16'];
        }

        $validator = Validator::make(
            $request->all(),
            [
                'nama_ibu' => $validatorNamaIbu,
                'nik_ibu' => $validatorNikIbu,
                'nama_ayah' => $validatorNamaAyah,
                'nik_ayah' => $validatorNikAyah,
                'alamat' => 'required',
                'desa_id' => 'required',
                'kecamatan_id' => 'required'
            ],
            [
                'nama_ibu.required' => 'Nama ibu tidak boleh kosong',
                'nik_ibu.required' => 'NIK tidak boleh kosong',
                'nik_ibu.unique' => 'NIK sudah ada',
                'nik_ibu.digits' => 'NIK harus terdiri dari 16 digit',
                'nama_ayah.required' => 'Nama ayah tidak boleh kosong',
                'nik_ayah.required' => 'NIK tidak boleh kosong',
                'nik_ayah.unique' => 'NIK sudah ada',
                'nik_ayah.digits' => 'NIK harus terdiri dari 16 digit',
                'alamat.required' => 'Alamat tidak boleh kosong',
                'desa_id.required' => 'Desa tidak boleh kosong',
                'kecamatan_id.required' => 'Kecamatan tidak boleh kosong',
            ]
        );

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()]);
        }

        $orangTua = new OrangTua();
        $orangTua->nama_ibu = $request->nama_ibu;
        $orangTua->nik_ibu = $request->nik_ibu;
        $orangTua->nama_ayah = $request->nama_ayah;
        $orangTua->nik_ayah = $request->nik_ayah;
        $orangTua->rt = $request->rt;
        $orangTua->rw = $request->rw;
        $orangTua->alamat = $request->alamat;
        $orangTua->desa_id = $request->desa_id;
        $orangTua->save();


        return response()->json(['status' => 'success']);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\OrangTua  $orangTua
     * @return \Illuminate\Http\Response
     */
    public function show(OrangTua $orangTua)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\OrangTua  $orangTua
     * @return \Illuminate\Http\Response
     */
    public function edit(OrangTua $orangTua)
    {
        return view('dashboard.pages.masterData.orangTua.edit', compact(['orangTua']));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\OrangTua  $orangTua
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, OrangTua $orangTua)
    {
        $namaIbu = $request->nama_ibu;
        $nikIbu = $request->nik_ibu;
        $namaAyah = $request->nama_ayah;
        $nikAyah = $request->nik_ayah;

        if (((!$namaIbu && !$nikIbu) && (!$namaAyah && !$nikAyah)) || (($namaIbu && !$nikIbu) || (!$namaIbu && $nikIbu) || ($namaIbu && $nikIbu)) || (($namaAyah && !$nikAyah) || (!$namaAyah && $nikAyah) || ($namaAyah && $nikAyah))) {
            $validatorNamaIbu = 'required';
            $validatorNikIbu = ['required', Rule::unique('orang_tua')->ignore($orangTua->id)->withoutTrashed(), 'digits:16'];
            $validatorNamaAyah = 'required';
            $validatorNikAyah = ['required', Rule::unique('orang_tua')->ignore($orangTua->id)->withoutTrashed(), 'digits:16'];
        } else if ((($namaIbu && !$nikIbu) || (!$namaIbu && $nikIbu) || ($namaIbu && $nikIbu))  && (!$namaAyah && !$nikAyah)) {
            $validatorNamaIbu = 'required';
            $validatorNikIbu = ['required', Rule::unique('orang_tua')->ignore($orangTua->id)->withoutTrashed(), 'digits:16'];
            $validatorNamaAyah = 'nullable';
            $validatorNikAyah = 'nullable';
        } else if ((($namaAyah && !$nikAyah) || (!$namaAyah && $nikAyah) || ($namaAyah && $nikAyah))  && (!$namaIbu && !$nikIbu)) {
            $validatorNamaIbu = 'nullable';
            $validatorNikIbu = 'nullable';
            $validatorNamaAyah = 'required';
            $validatorNikAyah = ['required', Rule::unique('orang_tua')->ignore($orangTua->id)->withoutTrashed(), 'digits:16'];
        }

        $validator = Validator::make(
            $request->all(),
            [
                'nama_ibu' => $validatorNamaIbu,
                'nik_ibu' => $validatorNikIbu,
                'nama_ayah' => $validatorNamaAyah,
                'nik_ayah' => $validatorNikAyah,
                'alamat' => 'required',
                'desa_id' => 'required',
                'kecamatan_id' => 'required'
            ],
            [
                'nama_ibu.required' => 'Nama ibu tidak boleh kosong',
                'nik_ibu.required' => 'NIK tidak boleh kosong',
                'nik_ibu.unique' => 'NIK sudah ada',
                'nik_ibu.digits' => 'NIK harus terdiri dari 16 digit',
                'nama_ayah.required' => 'Nama ayah tidak boleh kosong',
                'nik_ayah.required' => 'NIK tidak boleh kosong',
                'nik_ayah.unique' => 'NIK sudah ada',
                'nik_ayah.digits' => 'NIK harus terdiri dari 16 digit',
                'alamat.required' => 'Alamat tidak boleh kosong',
                'desa_id.required' => 'Desa tidak boleh kosong',
                'kecamatan_id.required' => 'Kecamatan tidak boleh kosong',
            ]
        );

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()]);
        }

        $orangTua->nama_ibu = $request->nama_ibu;
        $orangTua->nik_ibu = $request->nik_ibu;
        $orangTua->nama_ayah = $request->nama_ayah;
        $orangTua->nik_ayah = $request->nik_ayah;
        $orangTua->rt = $request->rt;
        $orangTua->rw = $request->rw;
        $orangTua->alamat = $request->alamat;
        $orangTua->desa_id = $request->desa_id;
        $orangTua->save();


        return response()->json(['status' => 'success']);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\OrangTua  $orangTua
     * @return \Illuminate\Http\Response
     */
    public function destroy(OrangTua $orangTua)
    {
        $orangTua->delete();

        return response()->json(['status' => 'success']);
    }
}