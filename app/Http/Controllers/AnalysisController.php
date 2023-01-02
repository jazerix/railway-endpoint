<?php

namespace App\Http\Controllers;

use App\Models\Joint;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Location\Coordinate;
use Location\Distance\Vincenty;
use MongoDB\BSON\ObjectId;
use MongoDB\Client;
use MongoDB\Database;
use MongoDB\Model\BSONDocument;

class AnalysisController extends Controller
{
    public function showUpload()
    {
        return view('upload');
    }

    /**
     * @throws \Exception
     */
    public function upload(Request $request)
    {
        set_time_limit(0);
        $client = new Client('mongodb://localhost:27017/railway');
        $db = $client->railway;
        $exists = $this->hasCollection($db, "measurements");
        if(!$exists)
            $db->createCollection("measurements");
        $collection = $db->measurements;
        $file = $request->file('file');
        $fp = fopen($file->path(), "rb");
        $recordingId = Str::of($request->file('file')->getClientOriginalName())->substr(0, -4);


        // $documentId = new ObjectId($entry->getInsertedId());
        $allData = [];
        while(!feof($fp)) {
            try {
                $data = [...unpack("s4", fread($fp, 8)), ...unpack("I", fread($fp, 4))];
                if($data[4] == 0) {
                    break;
                }
                $allData[] = [
                    'recording_id' => $recordingId->toString(),
                    'x'            => $this->convertToDecimal($data[0]),
                    'y'            => $this->convertToDecimal($data[1]),
                    'z'            => $this->convertToDecimal($data[2]),
                    't'            => $data[4]
                ];
                if(count($allData) == 5000) {
                    $collection->insertMany($allData);
                    $allData = [];
                }
            } catch(\Exception $e) {
                if(!feof($fp)) // didn't reach end of file
                    throw $e;
            }
        }

        if(count($allData) > 0)
            $collection->insertMany($allData);

        return redirect()->to('/');
    }

    private function convertToDecimal(int $value): float
    {
        $lsb = 0.00390625;
        return $lsb * $value;
    }

    /**
     * @param Database $db
     * @return true
     */
    private function hasCollection(Database $db, string $name): bool
    {
        foreach($db->listCollectionNames() as $collectionName) {
            if($collectionName == $name) {
                return true;
            }
        }
        return false;
    }

    public function measurements()
    {
        $client = new Client('mongodb://localhost:27017/railway');
        $db = $client->railway;
        $exists = $this->hasCollection($db, "measurements");
        if(!$exists)
            return [];
        $collection = $db->measurements;
        $results = $collection->aggregate([['$group' => ['_id' => '$recording_id', 'samples' => ['$count' => (object)[]]]]]);
        $recordings = collect();
        foreach($results as $result) {
            $recordings[] = [
                'recording_id' => $result->_id,
                'samples'      => $result->samples
            ];
        }

        if(!$this->hasCollection($db, "positions")) {
            return $recordings->map(fn($recording) => [
                'recording_id'  => (int)$recording['recording_id'],
                'samples'       => number_format($recording['samples']),
                'has_positions' => false
            ]);
        }

        $groupedPositions = $db->positions->aggregate([['$group' => ['_id' => '$recording_id', 'first' => ['$first' => '$$ROOT'], 'last' => ['$last' => '$$ROOT']]]]);
        $recordings = $recordings->keyBy('recording_id')->toArray();
        foreach($groupedPositions as $groupedPosition) {
            if(!array_key_exists($groupedPosition->_id, $recordings))
                continue;
            $recordings[$groupedPosition->_id]['has_positions'] = true;
            $recordings[$groupedPosition->_id]['first'] = $groupedPosition['first'];
            $recordings[$groupedPosition->_id]['last'] = $groupedPosition['last'];
            $recordings[$groupedPosition->_id]['samples'] = number_format($recordings[$groupedPosition->_id]['samples']);
        }

        return array_values($recordings);
    }

    public function uploadPositions(Request $request, string $recordingId)
    {

        $file = $request->file('file');
        preg_match('/recording-([0-9]+)/', $file->getClientOriginalName(), $matches);
        $fileRecId = $matches[1];
        abort_if($fileRecId != $recordingId, 400, "Uploaded recording does not match.");

        set_time_limit(0);
        $client = new Client('mongodb://localhost:27017/railway');
        $db = $client->railway;
        $exists = $this->hasCollection($db, "positions");
        if(!$exists)
            $db->createCollection("positions");
        $collection = $db->positions;
        $fp = fopen($file->path(), "r");
        while(($line = fgets($fp)) !== false) {
            $parts = explode(",", $line);
            $time = $parts[0];
            preg_match('/[0-9.]+/', $time, $timeParts);
            $time = (float)$timeParts[0];
            $lat = (float)$parts[1];
            $long = (float)trim($parts[2]);
            $collection->insertOne([
                'recording_id' => $recordingId,
                't'            => $time,
                'coordinates'  => [
                    $long,
                    $lat
                ]
            ]);
        }
        fclose($fp);
        return redirect()->to('/');
    }

    public function positions(string $recordingId)
    {
        set_time_limit(0);
        $client = new Client('mongodb://localhost:27017/railway');
        $db = $client->railway;
        $collection = $db->positions;
        $positions = $collection->find([
            'recording_id' => $recordingId
        ]);
        $data = [];
        foreach($positions as $position) {
            $data[] = [
                't'    => $position->t,
                'lat'  => $position->coordinates[1],
                'long' => $position->coordinates[0]
            ];
        }
        return $data;
    }

    public function closest(string $recordingId)
    {
        $joint = Joint::find(request('joint'));
        $latitude = $joint->coordinates->latitude;
        $longitude = $joint->coordinates->longitude;

        $client = new Client('mongodb://localhost:27017/railway');
        $db = $client->railway;
        $collection = $db->positions;
        $results = $collection->aggregate([['$geoNear' => ['near' => ['type' => 'Point', 'coordinates' => [$longitude, $latitude]], 'distanceField' => 'distance', 'maxDistance' => 1000, 'spherical' => true, 'query' => ['recording_id' => "$recordingId"]]]]);

        $points = [];
        foreach($results as $result) {
            $points[] = [
                'coordinates' => $result['coordinates'],
                'distance'    => $result['distance'],
                'time'        => $result['t']
            ];
        }

        return $points;
    }

    public function data(string $recordingId)
    {
        $client = new Client('mongodb://localhost:27017/railway');
        $db = $client->railway;
        $collection = $db->positions;
        $originData = $collection->find(['recording_id' => $recordingId, 't' => ['$gte' => ((int)\request('t'))]]);
        $origin = null;

        $points = collect();
        $relevantPoints = [];
        $currentCm = 1;
        foreach($originData as $d) {
            if($origin == null) {
                $origin = new Coordinate($d->coordinates[1], $d->coordinates[0]);
                $points[] = [
                    'cm'   => 0,
                    'lat'  => $origin->getLat(),
                    'long' => $origin->getLng(),
                    't'    => 0
                ];
                continue;
            }
            $endPoint = new Coordinate($d->coordinates[1], $d->coordinates[0]);
            $duration = $d->t - (int)\request('t');

            $distance = $origin->getDistance($endPoint, new Vincenty());

            $kmT = $distance / $duration * 3600;
            $durationPerCm = $duration / floor($distance * 100);
            $requiredPoints = floor($distance * 100) > (float)\request('distance') ? (float)\request('distance') : floor($distance * 100);


            $startTime = request('t');

            $latPerStep = ($endPoint->getLat() - $origin->getLat()) / $requiredPoints;
            $longPerStep = ($endPoint->getLng() - $origin->getLng()) / $requiredPoints;
            for($i = 0; $i < $requiredPoints; $i++) {
                $points[] = [
                    'cm'   => $currentCm++,
                    'lat'  => $origin->getLat() + (($i + 1) * $latPerStep),
                    'long' => $origin->getLng() + (($i + 1) * $longPerStep),
                    't'    => ($i + 1) * $durationPerCm
                ];
            }


            $origin = $endPoint;
            if($distance * 100 > \request('distance'))
                break;
        }
        abort_if($origin == null, 500);


        $relevantMeasurements = collect($db->measurements->find([
            'recording_id' => $recordingId,
            't'            => [
                '$gte' => (int)$startTime,
                '$lte' => (int)ceil($d->t)
            ]
        ])->toArray());
        $current = [];
        $relevantMeasurements->each(function(BSONDocument $document) use (&$current) {
            $key = $document['t'] * 10;
            if(array_key_exists($key, $current))
                $key += 5;
            $current[$key] = [
                'x' => $document['x'],
                'y' => $document['y'],
                'z' => $document['z']
            ];
        });

        foreach($points as $point) {
            $actualTime = (int)($startTime + $point['t']) * 10;
            if(array_key_exists($actualTime, $current) && !array_key_exists($actualTime, $relevantPoints)) {
                $relevantPoints[$actualTime] = [...$point, 'data' => $current[$actualTime]];
                continue;
            }
            if(array_key_exists($actualTime + 5, $current) && !array_key_exists($actualTime + 5, $relevantPoints)) {
                $relevantPoints[$actualTime + 5] = [...$point, 'data' => $current[$actualTime]];
                continue;
            }
        }


        $values = collect(array_values($relevantPoints));

        $labels = $values->map(fn($v) => $v['cm']);

        //return $values->pluck('data.y')->join(',');

        $sampleRate = 1060;
        $n = $values->pluck('data.x')->padToNearest();
        return [
            'kmt'    => $kmT,
            'points' => [
                'x'           => $values->pluck('data.x'),
                'y'           => $values->pluck('data.y'),
                'z'           => $values->pluck('data.z'),
                'fourier'     => [
                    'x' => FFT::magnitude($values->pluck('data.x')->padToNearest()->toArray()),
                    'y' => FFT::magnitude($values->pluck('data.y')->padToNearest()->toArray()),
                    'z' => FFT::magnitude($values->pluck('data.z')->map(fn($v) => $v - 1)->padToNearest()->toArray()),
                    'labels' => $n->map(fn($current, $i) => $i * $sampleRate / $n->count())
                ],
                'coordinates' => $values->map(fn($v) => ['lat' => $v['lat'], 'long' => $v['long']]),
                'labels'      => $labels
            ]];
    }

    /**
     * From: https://gist.github.com/mbijon/1332348
     * @param $input
     * @param $isign
     * @return mixed
     */
    function fft($input, $isign)
    {

        $padTo = pow(2, ceil(log(count($input), 2)));
        for($i = count($input); $i < $padTo; $i++)
            $input[] = 0;

        #####################################################################
        # We need to shift the array up one because this script is a direct
        # port of the fortran program from NR.  Should fix in future.
        #####################################################################
        $data[0] = 0;
        for($i = 0; $i < count($input); $i++)
            $data[($i + 1)] = $input[$i];

        $n = count($input);

        $j = 1;

        for($i = 1; $i < $n; $i += 2) {
            if($j > $i) {
                list($data[($j + 0)], $data[($i + 0)]) = array($data[($i + 0)], $data[($j + 0)]);
                list($data[($j + 1)], $data[($i + 1)]) = array($data[($i + 1)], $data[($j + 1)]);
            }

            $m = $n >> 1;

            while(($m >= 2) && ($j > $m)) {
                $j -= $m;
                $m = $m >> 1;
            }

            $j += $m;

        }

        $mmax = 2;

        while($n > $mmax) {  # Outer loop executed log2(nn) times
            $istep = $mmax << 1;

            $theta = $isign * 2 * pi() / $mmax;

            $wtemp = sin(0.5 * $theta);
            $wpr = -2.0 * $wtemp * $wtemp;
            $wpi = sin($theta);

            $wr = 1.0;
            $wi = 0.0;
            for($m = 1; $m < $mmax; $m += 2) {  # Here are the two nested inner loops
                for($i = $m; $i <= $n; $i += $istep) {

                    $j = $i + $mmax;

                    $tempr = $wr * $data[$j] - $wi * $data[($j + 1)];
                    $tempi = $wr * $data[($j + 1)] + $wi * $data[$j];

                    $data[$j] = $data[$i] - $tempr;
                    $data[($j + 1)] = $data[($i + 1)] - $tempi;

                    $data[$i] += $tempr;
                    $data[($i + 1)] += $tempi;

                }
                $wtemp = $wr;
                $wr = ($wr * $wpr) - ($wi * $wpi) + $wr;
                $wi = ($wi * $wpr) + ($wtemp * $wpi) + $wi;
            }
            $mmax = $istep;
        }

        for($i = 1; $i < count($data); $i++) {
            $data[$i] *= sqrt(2 / $n);                   # Normalize the data
            if(abs($data[$i]) < 1E-8)
                $data[$i] = 0;  # Let's round small numbers to zero
            $input[($i - 1)] = $data[$i];                # We need to shift array back (see beginning)
        }

        return $input;

    }


}
