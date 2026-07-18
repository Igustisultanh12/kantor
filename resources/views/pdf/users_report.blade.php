<!DOCTYPE html>
<html>
<head>
    <title>Rekap Personel</title>
    <style>
        body { font-family: sans-serif; text-transform: uppercase; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid black; padding: 8px; text-align: center; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid black; padding-bottom: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h2 style="margin:0;">{{ $title }}</h2>
        <h3 style="margin:5px 0;">{{ $unit }}</h3>
        <p style="margin:0; font-size: 8px;">Dicuplik pada: {{ $date }}</p>
    </div>

    <table>
        <thead>
            <tr style="background-color: #f2f2f2;">
                <th>No</th>
                <th>Nama Personel</th>
                <th>Pangkat</th>
                <th>NRP</th>
                <th>Role</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $index => $u)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td style="text-align: left;">{{ $u->name }}</td>
                <td>{{ $u->pangkat }}</td>
                <td>{{ $u->nrp }}</td>
                <td>{{ strtoupper($u->role) }}</td>
                <td>{{ $u->is_active ? 'AKTIF' : 'SUSPEND' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>