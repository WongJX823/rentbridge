<?php
/**
 * RentBridge Seed Data Generator
 * Outputs valid SQL INSERT statements to stdout.
 * Run: php seed_generate.php > seed_data.sql
 */

// ── helpers ───────────────────────────────────────────────────────────────────
function q(string $s): string { return "'" . addslashes($s) . "'"; }
function qn($v): string { return $v === null ? 'NULL' : q((string)$v); }
function qi($v): string { return $v === null ? 'NULL' : (string)(int)$v; }
function qf($v): string { return $v === null ? 'NULL' : number_format((float)$v, 2, '.', ''); }

$pw = '$2y$10$UxOTjCguXl9fcWWaNvvjtuxZfqHP2vM7Hba4BYob0n455/Hsv1s3y';

// ── name pools ────────────────────────────────────────────────────────────────
$malay_first = ['Ahmad','Muhammad','Mohd','Amirul','Hafiz','Faizal','Azri','Syafiq','Haziq','Izzat',
    'Farhana','Nurul','Siti','Aini','Zulaikha','Nabilah','Aisyah','Hanis','Liyana','Suraya',
    'Arif','Zikri','Irfan','Ridhwan','Nadzmi','Aliya','Husna','Sofiah','Najwa','Hidayah'];
$malay_last  = ['bin Abdullah','bin Razak','bin Hassan','bin Ibrahim','binti Yusof','binti Ahmad',
    'bin Zainudin','bin Kamaruddin','binti Hamid','bin Saad','bin Rahim','binti Ismail',
    'bin Othman','binti Nordin','bin Zainal','binti Idris','bin Mansor','binti Latif'];
$chinese_first = ['Wei','Jia Hui','Kai Xin','Zi Yang','Ming Hao','Shu Ting','Jing Yi','De Wei',
    'Xin Yi','Rui','Jun Jie','Li Ying','Wan Ting','Zhi Hao','Yong Kang','Su Lin','Hui Min','Kok Wei'];
$chinese_last  = ['Tan','Lim','Wong','Lee','Ng','Chan','Loh','Ong','Goh','Chong','Yap','Chin','Khor'];
$indian_first  = ['Vijay','Priya','Arjun','Kavitha','Santhosh','Divya','Rajan','Meena','Suresh','Anitha',
    'Karthik','Lakshmi','Deepak','Nisha','Ganesh'];
$indian_last   = ['a/l Kumar','a/l Raj','a/l Selvam','a/p Muthu','a/p Rajan','a/l Krishnan',
    'a/p Subramaniam','a/l Nair','a/p Pillai','a/l Govindasamy'];

$malay_ic_prefix = ['960','970','980','990','000','010','020'];

$landlord_first = ['Roslan','Hairul','Khairul','Azman','Noraini','Zainab','Salmah','Rashid',
    'David','Steven','Kevin','Jenny','Michael','Patrick','Thomas','Susan','Helen',
    'Ravi','Siva','Muthu'];
$landlord_last  = ['bin Idris','bin Osman','bin Ghani','binti Salleh','binti Musa',
    'Tan','Lim','Wong','Lee','Ng','a/l Krishnan','a/l Subramaniam','a/l Raj'];

$areas = [
    ['city'=>'Durian Tunggal','postcode'=>'76100','state'=>'Melaka',
     'lat'=>'2.4937000','lng'=>'102.2048000',
     'streets'=>['Jalan Durian Tunggal','Lorong Bunga Raya','Jalan Pahlawan','Jalan Pulai']],
    ['city'=>'Ayer Keroh','postcode'=>'75450','state'=>'Melaka',
     'lat'=>'2.2721000','lng'=>'102.2854000',
     'streets'=>['Jalan Ayer Keroh Lama','Jalan Tasik Utama','Jalan Muzaffar','Persiaran Bukit Beruang']],
    ['city'=>'Ayer Keroh','postcode'=>'75450','state'=>'Melaka',
     'lat'=>'2.2650000','lng'=>'102.2800000',
     'streets'=>['Taman Tasik Utama','Jalan Tasik Indah','Lorong Tasik 1','Lorong Tasik 2']],
    ['city'=>'Durian Tunggal','postcode'=>'76100','state'=>'Melaka',
     'lat'=>'2.5010000','lng'=>'102.1990000',
     'streets'=>['Scientex Durian Tunggal','Jalan Scientex 1','Jalan Scientex 2','Persiaran Scientex']],
];

$facilities_pool = [
    'WiFi,Air Conditioning,Washing Machine,Kitchen',
    'WiFi,Air Conditioning,Water Heater,Kitchen,Parking',
    'WiFi,Air Conditioning,Washing Machine,Refrigerator,Kitchen,Parking',
    'WiFi,Air Conditioning,Wardrobe,Study Table,Kitchen',
    'WiFi,Air Conditioning,Wardrobe,Study Table,Washing Machine,Parking',
    'WiFi,Air Conditioning,Swimming Pool,Gym,Parking,Security',
    'WiFi,Fan,Washing Machine,Kitchen,Parking',
    'WiFi,Air Conditioning,Water Heater,Wardrobe,Study Table,Kitchen,Parking,Security',
];

$room_rents   = [280,290,300,310,320,280,290,310,300,320,285,295,305,315,280];
$studio_rents = [700,720,750,780,800,720,750,800,700,780,750,720,800,700,750];
$unit_rents   = [1000,1050,1100,1150,1200,1000,1100,1050,1200,1100,1150,1000,1200,1050,1100];

$home_states  = ['Selangor','Johor','Kedah','Perak','Sabah','Sarawak','Kelantan','Terengganu','Pahang','Negeri Sembilan'];
$cities_pref  = ['Durian Tunggal','Ayer Keroh','Melaka Tengah','Bukit Beruang','Ayer Keroh','Durian Tunggal'];
$agents       = [15,16,34,35];

// ── output buffer ─────────────────────────────────────────────────────────────
$sql = [];
$sql[] = "SET FOREIGN_KEY_CHECKS=0;";
$sql[] = "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';";
$sql[] = "SET NAMES utf8mb4;";
$sql[] = "";

// ═══════════════════════════════════════════════════════════════════════════════
// 1. STUDENTS  (user IDs 36–235)
// ═══════════════════════════════════════════════════════════════════════════════
$sql[] = "-- ============================================================";
$sql[] = "-- STUDENTS (user IDs 36–235)";
$sql[] = "-- ============================================================";

$student_data = []; // keyed by user_id

function make_student_name(int $uid, array $mp): array {
    $r = $uid % 10;
    if ($r < 5) { // Malay ~50%
        $fi = $mp['malay_first'][$uid % count($mp['malay_first'])];
        $li = $mp['malay_last'][$uid % count($mp['malay_last'])];
        $full = $fi . ' ' . $li;
        $pref = $fi;
        $email_first = strtolower(preg_replace('/\s+/', '.', $fi));
        $email_last  = strtolower(preg_replace('/[^a-z]/', '', str_replace(['bin ','binti '], '', $li)));
    } elseif ($r < 8) { // Chinese ~30%
        $fi = $mp['chinese_first'][($uid * 3) % count($mp['chinese_first'])];
        $li = $mp['chinese_last'][($uid * 7) % count($mp['chinese_last'])];
        $full = $li . ' ' . $fi;
        $pref = explode(' ', $fi)[0];
        $email_first = strtolower(str_replace(' ', '.', $fi));
        $email_last  = strtolower($li);
    } else { // Indian ~20%
        $fi = $mp['indian_first'][($uid * 2) % count($mp['indian_first'])];
        $li = $mp['indian_last'][($uid * 5) % count($mp['indian_last'])];
        $full = $fi . ' ' . $li;
        $pref = $fi;
        $email_first = strtolower($fi);
        $email_last  = strtolower(preg_replace('/[^a-z]/', '', str_replace(['a/l ','a/p '], '', $li)));
    }
    return ['full'=>$full,'pref'=>$pref,'email_first'=>$email_first,'email_last'=>$email_last];
}

$name_pool = [
    'malay_first'=>$malay_first,'malay_last'=>$malay_last,
    'chinese_first'=>$chinese_first,'chinese_last'=>$chinese_last,
    'indian_first'=>$indian_first,'indian_last'=>$indian_last,
];

$sem_ranges = [
    1 => ['start_id'=>36,  'count'=>50, 'date_start'=>'2024-01-15','date_end'=>'2024-07-31','matric_prefix'=>'B03241'],
    2 => ['start_id'=>86,  'count'=>70, 'date_start'=>'2024-08-01','date_end'=>'2025-01-31','matric_prefix'=>'B03242'],
    3 => ['start_id'=>156, 'count'=>80, 'date_start'=>'2025-02-01','date_end'=>'2025-07-31','matric_prefix'=>'B03243'],
];

$used_emails = [];

foreach ($sem_ranges as $sem => $sr) {
    $sql[] = "";
    $sql[] = "-- Sem $sem students";
    for ($i = 0; $i < $sr['count']; $i++) {
        $uid = $sr['start_id'] + $i;
        $nm  = make_student_name($uid, $name_pool);

        $base_email = $nm['email_first'] . '.' . $nm['email_last'] . '@student.utem.edu.my';
        $email = $base_email;
        $suffix = 1;
        while (isset($used_emails[$email])) {
            $email = $nm['email_first'] . '.' . $nm['email_last'] . $suffix . '@student.utem.edu.my';
            $suffix++;
        }
        $used_emails[$email] = true;

        // ~5% suspended  (scenario 9: user 100 suspended in Sem2)
        $status = (($uid % 20) === 0 || $uid === 100 || $uid === 105) ? 'suspended' : 'active';

        $days_range = max(1, (int)((strtotime($sr['date_end']) - strtotime($sr['date_start'])) / 86400));
        $day_offset = ($i * 3) % $days_range;
        $created = date('Y-m-d H:i:s', strtotime($sr['date_start']) + $day_offset * 86400 + ($uid % 86400));

        $matric = $sr['matric_prefix'] . str_pad($i + 1, 4, '0', STR_PAD_LEFT);
        $phone  = '01' . (($uid % 9) + 1) . '-' . str_pad(($uid * 1234567) % 10000000, 7, '0', STR_PAD_LEFT);
        $pref_city = $cities_pref[$uid % count($cities_pref)];
        $max_rent  = 300 + (($uid % 5) * 50);
        $movein    = ($sem === 1) ? '2024-02-01' : (($sem === 2) ? '2024-09-01' : '2025-02-01');

        $bios = [
            'Looking for a quiet room near UTeM campus.',
            'Prefer house-sharing with other students.',
            'Need WiFi and air conditioning. Budget-conscious.',
            'Final year student, need stable accommodation.',
            'Friendly and clean. Looking for affordable housing.',
            'Prefer female-only housing near campus.',
            'Engineering student, need study space.',
            'Part-time worker, flexible on move-in date.',
        ];
        $bio = $bios[$uid % count($bios)];

        $sql[] = "INSERT IGNORE INTO users (id,email,password_hash,primary_role,status,last_used_role,created_at,updated_at) VALUES "
            . "({$uid}," . q($email) . "," . q($pw) . ",'student'," . q($status) . ",'student'," . q($created) . "," . q($created) . ");";

        // students table: no allow_whatsapp column
        $sql[] = "INSERT IGNORE INTO students (user_id,full_name,preferred_name,avatar_path,matric_no,university,phone,looking_for_housing,housing_pref_city,housing_pref_max_rent,housing_pref_move_in,housing_bio) VALUES "
            . "({$uid}," . q($nm['full']) . "," . q($nm['pref']) . ",NULL," . q($matric) . ",'UTeM'," . q($phone) . ",1,"
            . q($pref_city) . ",{$max_rent}," . q($movein) . "," . q($bio) . ");";

        $student_data[$uid] = ['email'=>$email,'full'=>$nm['full'],'status'=>$status,'sem'=>$sem,'created'=>$created];
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// 2. LANDLORDS (user IDs 236–270)
// ═══════════════════════════════════════════════════════════════════════════════
$sql[] = "";
$sql[] = "-- ============================================================";
$sql[] = "-- LANDLORDS (user IDs 236–270)";
$sql[] = "-- ============================================================";

$landlord_sem_ranges = [
    1 => ['start_id'=>236,'count'=>15,'date_start'=>'2024-01-10','date_end'=>'2024-06-30'],
    2 => ['start_id'=>251,'count'=>10,'date_start'=>'2024-08-01','date_end'=>'2025-01-15'],
    3 => ['start_id'=>261,'count'=>10,'date_start'=>'2025-02-01','date_end'=>'2025-06-30'],
];

$landlord_data = [];

foreach ($landlord_sem_ranges as $sem => $lr) {
    $sql[] = "";
    $sql[] = "-- Sem $sem landlords";
    for ($i = 0; $i < $lr['count']; $i++) {
        $uid  = $lr['start_id'] + $i;
        $fi   = $landlord_first[$uid % count($landlord_first)];
        $li   = $landlord_last[$uid % count($landlord_last)];
        $full = $fi . ' ' . $li;
        $pref = $fi;
        $email = strtolower(preg_replace('/[^a-z0-9]/', '.', $fi)) . '.' . strtolower(preg_replace('/[^a-z0-9]/', '', $li)) . $uid . '@gmail.com';

        // unique IC: prefix + padded uid
        $ic_pfx = $malay_ic_prefix[$uid % count($malay_ic_prefix)];
        $ic     = $ic_pfx . str_pad($uid % 100000, 6, '0', STR_PAD_LEFT) . '-' . str_pad($uid % 99, 2, '0', STR_PAD_LEFT) . '-' . str_pad($uid % 9999, 4, '0', STR_PAD_LEFT);

        $phone    = '01' . (($uid % 9) + 1) . '-' . str_pad(($uid * 7654321) % 10000000, 7, '0', STR_PAD_LEFT);
        $verified = ($uid % 5 === 0) ? 0 : 1;
        $allow_wa = ($uid % 4 !== 0) ? 1 : 0;

        $days_range = max(1, (int)((strtotime($lr['date_end']) - strtotime($lr['date_start'])) / 86400));
        $day_offset = ($i * 4) % $days_range;
        $created = date('Y-m-d H:i:s', strtotime($lr['date_start']) + $day_offset * 86400);

        $address = 'No. ' . ($i + 1) . ', Jalan Melaka Maju ' . $sem . ', Melaka';

        $sql[] = "INSERT IGNORE INTO users (id,email,password_hash,primary_role,status,last_used_role,created_at,updated_at) VALUES "
            . "({$uid}," . q($email) . "," . q($pw) . ",'landlord','active','landlord'," . q($created) . "," . q($created) . ");";

        $sql[] = "INSERT IGNORE INTO landlords (user_id,full_name,preferred_name,avatar_path,ic_no,phone,allow_whatsapp,address,verified) VALUES "
            . "({$uid}," . q($full) . "," . q($pref) . ",NULL," . q($ic) . "," . q($phone) . ",{$allow_wa}," . q($address) . ",{$verified});";

        $landlord_data[$uid] = ['full'=>$full,'sem'=>$sem,'verified'=>$verified,'created'=>$created];
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// 3. PROPERTIES (IDs start at 34)
// ═══════════════════════════════════════════════════════════════════════════════
$sql[] = "";
$sql[] = "-- ============================================================";
$sql[] = "-- PROPERTIES (IDs 34–138)";
$sql[] = "-- ============================================================";

$prop_sem_ranges = [
    1 => ['start_id'=>34,  'count'=>40,'date_start'=>'2024-01-20','date_end'=>'2024-04-30'],
    2 => ['start_id'=>74,  'count'=>35,'date_start'=>'2024-08-05','date_end'=>'2024-11-30'],
    3 => ['start_id'=>109, 'count'=>30,'date_start'=>'2025-02-05','date_end'=>'2025-05-31'],
];

$prop_types_map   = [];
$prop_rents_map   = [];
$prop_landlord_map= [];
$prop_status_map  = [];
$prop_agent_map   = [];

$img_counter    = 1000;
$doc_counter    = 1000;
$assign_counter = 1;

function landlord_for_prop(int $prop_i, int $sem, array $landlord_sem_ranges): int {
    if ($sem === 1)      $pool = range(236, 250);
    elseif ($sem === 2)  $pool = array_merge(range(236,250), range(251,260));
    else                 $pool = array_merge(range(236,250), range(251,260), range(261,270));
    return $pool[$prop_i % count($pool)];
}

$prop_descs = [
    'Comfortable room with attached bathroom, suitable for UTeM students. Close to campus bus stop.',
    'Fully furnished studio unit with modern amenities. Quiet neighbourhood.',
    'Spacious whole unit suitable for group of students. Near supermarket and food court.',
    'Single room with air conditioning and WiFi. Shared kitchen and bathroom.',
    'Master bedroom with private bathroom in a shared house.',
    'Studio apartment with city view. Fully furnished and ready to move in.',
    'Double-storey house unit for rent. 3 rooms available for students.',
    'Cozy room near UTeM main gate. 5 minutes by bus to faculty.',
];

foreach ($prop_sem_ranges as $sem => $pr) {
    $sql[] = "";
    $sql[] = "-- Sem $sem properties";
    for ($i = 0; $i < $pr['count']; $i++) {
        $pid  = $pr['start_id'] + $i;
        $area = $areas[$pid % count($areas)];
        $street  = $area['streets'][$pid % count($area['streets'])];
        $address = 'No. ' . ($pid % 50 + 1) . ', ' . $street . ', ' . $area['city'] . ', Melaka';

        // type distribution: 60% room, 25% whole_unit, 15% studio
        $type_r = $pid % 20;
        if ($type_r < 12)      { $ptype='room';       $rent=$room_rents[$pid%count($room_rents)]; }
        elseif ($type_r < 17)  { $ptype='whole_unit'; $rent=$unit_rents[$pid%count($unit_rents)]; }
        else                   { $ptype='studio';     $rent=$studio_rents[$pid%count($studio_rents)]; }

        $prop_types_map[$pid] = $ptype;
        $prop_rents_map[$pid] = $rent;

        // scenario 5: prop 80 must be whole_unit (Sem2 group tenancy)
        if ($pid === 80) { $ptype='whole_unit'; $prop_types_map[$pid]='whole_unit'; $rent=1100; $prop_rents_map[$pid]=1100; }

        $landlord_id = landlord_for_prop($i, $sem, $landlord_sem_ranges);
        // scenario 6: landlord 238 has props 40,41,42
        if ($pid === 40 || $pid === 41 || $pid === 42) $landlord_id = 238;
        $prop_landlord_map[$pid] = $landlord_id;

        $furnishing = ['none','partial','full'][$pid % 3];

        // status logic
        if ($sem === 1) {
            $status = ($pid % 15 === 0) ? 'rejected' : (($pid % 8 === 0) ? 'available' : 'rented');
        } elseif ($sem === 2) {
            $status = ($pid % 12 === 0) ? 'pending_approval' : (($pid % 7 === 0) ? 'available' : 'rented');
        } else {
            $status = ($pid % 10 === 0) ? 'pending_approval' : (($pid % 5 === 0) ? 'available' : 'rented');
        }
        // scenario 6
        if ($pid === 40) $status = 'rented';
        if ($pid === 41) $status = 'rented';
        if ($pid === 42) $status = 'available';
        // scenario 3: prop 34 rented in Sem1
        if ($pid === 34) $status = 'rented';
        $prop_status_map[$pid] = $status;

        // agent assignment
        $agent_id        = null;
        $agent_stat      = null;
        $agent_assigned_at = null;
        if (in_array($status, ['available','rented'])) {
            $agent_id          = $agents[$pid % count($agents)];
            $agent_stat        = 'accepted';
            $agent_assigned_at = date('Y-m-d H:i:s', strtotime($pr['date_start']) + $i * 86400 * 2);
        }
        $prop_agent_map[$pid] = $agent_id;

        // scenario 10: agent 15 handles both landlord_led and agent_led properties
        if ($agent_id === 15) {
            $viewing_mode = ($pid % 2 === 0) ? 'landlord_led' : 'agent_led';
        } else {
            $viewing_mode = ['landlord_led','agent_led','either'][$pid % 3];
        }

        $deposit    = $rent * 2;
        $facilities = $facilities_pool[$pid % count($facilities_pool)];
        $desc       = $prop_descs[$pid % count($prop_descs)];

        $days_range = max(1, (int)((strtotime($pr['date_end']) - strtotime($pr['date_start'])) / 86400));
        $day_offset = ($i * 2) % $days_range;
        $created    = date('Y-m-d H:i:s', strtotime($pr['date_start']) + $day_offset * 86400);

        $maps_url = 'https://maps.google.com/?q=' . $area['lat'] . ',' . $area['lng'];

        $agent_id_sql      = $agent_id        ? qi($agent_id)          : 'NULL';
        $agent_stat_sql    = $agent_stat      ? q($agent_stat)         : 'NULL';
        $agent_at_sql      = $agent_assigned_at ? q($agent_assigned_at) : 'NULL';
        $agent_ver_at_sql  = $agent_stat      ? q(date('Y-m-d H:i:s', strtotime($agent_assigned_at) + 86400 * 3)) : 'NULL';
        $agent_ver_by_sql  = $agent_stat      ? qi($agent_id)          : 'NULL';

        $sql[] = "INSERT IGNORE INTO properties (id,landlord_id,title,property_type,address,city,postcode,state,latitude,longitude,maps_url,monthly_rent,deposit,description,facilities,furnishing,status,assigned_agent_id,agent_assigned_at,agent_status,viewing_mode,agent_verified_at,agent_verified_by,created_at,updated_at) VALUES "
            . "({$pid},{$landlord_id},"
            . q('UTeM Area ' . ucfirst($ptype) . ' #' . $pid) . ','
            . q($ptype) . ',' . q($address) . ',' . q($area['city']) . ',' . q($area['postcode']) . ',' . q($area['state']) . ','
            . q($area['lat']) . ',' . q($area['lng']) . ',' . q($maps_url) . ','
            . qf($rent) . ',' . qf($deposit) . ',' . q($desc) . ',' . q($facilities) . ',' . q($furnishing) . ','
            . q($status) . ',' . $agent_id_sql . ',' . $agent_at_sql . ',' . $agent_stat_sql . ','
            . q($viewing_mode) . ',' . $agent_ver_at_sql . ',' . $agent_ver_by_sql . ','
            . q($created) . ',' . q($created) . ");";

        // property_images
        $sql[] = "INSERT IGNORE INTO property_images (id,property_id,image_path,is_primary,uploaded_at) VALUES ({$img_counter},{$pid}," . q('uploads/properties/'.$pid.'/img1.jpg') . ",1," . q($created) . ");";
        $img_counter++;
        $sql[] = "INSERT IGNORE INTO property_images (id,property_id,image_path,is_primary,uploaded_at) VALUES ({$img_counter},{$pid}," . q('uploads/properties/'.$pid.'/img2.jpg') . ",0," . q($created) . ");";
        $img_counter++;

        // property_documents
        $sql[] = "INSERT IGNORE INTO property_documents (id,property_id,document_type,file_path,original_name,file_size,mime_type,uploaded_by,uploaded_at,notes) VALUES "
            . "({$doc_counter},{$pid},'ownership_proof'," . q('uploads/docs/'.$pid.'/ownership.pdf') . ","
            . q('ownership_proof.pdf') . ",204800,'application/pdf',{$landlord_id}," . q($created) . ",NULL);";
        $doc_counter++;

        // property_agent_assignments
        if ($agent_id) {
            $sql[] = "INSERT IGNORE INTO property_agent_assignments (id,property_id,agent_id,round_number,assigned_at,responded_at,outcome) VALUES "
                . "({$assign_counter},{$pid},{$agent_id},1," . q($agent_assigned_at) . ","
                . q(date('Y-m-d H:i:s', strtotime($agent_assigned_at) + 3600 * 4)) . ",'accepted');";
            $assign_counter++;
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// 4. TENANCIES
// ═══════════════════════════════════════════════════════════════════════════════
$sql[] = "";
$sql[] = "-- ============================================================";
$sql[] = "-- TENANCIES";
$sql[] = "-- ============================================================";

$tenancy_id = 1;
$tenancies_db = []; // bid => data

function tenancy_status_pick(int $n): string {
    $r = $n % 100;
    if ($r < 35) return 'active';
    if ($r < 65) return 'completed';
    if ($r < 75) return 'rejected_by_landlord';
    if ($r < 83) return 'cancelled_by_student';
    if ($r < 88) return 'cancelled_by_landlord';
    if ($r < 92) return 'agent_verifying';
    if ($r < 96) return 'contract_pending';
    return 'pending_landlord';
}

function make_tenancy_sql(array $b): string {
    // cancelled_by must be int (user_id) or NULL
    $cancelled_by_val = 'NULL';
    if ($b['bstatus'] === 'cancelled_by_student') $cancelled_by_val = qi($b['student_id']);
    if ($b['bstatus'] === 'cancelled_by_landlord') $cancelled_by_val = qi($b['landlord_id']);

    $signed_path = 'NULL'; $signed_at = 'NULL'; $signed_by = 'NULL';
    if (in_array($b['bstatus'], ['active','completed','contract_pending'])) {
        $signed_path = q('uploads/contracts/tenancy_'.$b['bid'].'_signed.pdf');
        $signed_at   = q(date('Y-m-d H:i:s', strtotime($b['start']) - 7 * 86400));
        $signed_by   = qi($b['student_id']); // int FK
    }

    $landlord_resp = 'NULL';
    $cancel_reason = 'NULL';
    if ($b['bstatus'] === 'rejected_by_landlord') $landlord_resp = q('Sorry, already rented to a walk-in tenant');
    if ($b['bstatus'] === 'cancelled_by_student')  $cancel_reason = q('Found another place closer to campus');
    if ($b['bstatus'] === 'cancelled_by_landlord') $cancel_reason = q('Property is no longer available');

    return "INSERT IGNORE INTO tenancies (id,student_id,property_id,landlord_id,agent_id,start_date,end_date,duration_type,monthly_rent,deposit,status,signed_contract_path,signed_uploaded_at,signed_uploaded_by,student_note,landlord_response,cancellation_reason,cancelled_by,created_at,updated_at) VALUES "
        . "({$b['bid']},{$b['student_id']},{$b['prop_id']},{$b['landlord_id']},{$b['agent_id']},"
        . q($b['start']) . ',' . q($b['end']) . ',' . q($b['dur']) . ','
        . qf($b['rent']) . ',' . qf($b['rent']*2) . ',' . q($b['bstatus']) . ','
        . $signed_path . ',' . $signed_at . ',' . $signed_by . ','
        . q('I am interested in this property. Please consider my application.') . ','
        . $landlord_resp . ',' . $cancel_reason . ',' . $cancelled_by_val . ','
        . q($b['created']) . ',' . q($b['created']) . ");";
}

$tenancy_sem_cfg = [
    1 => ['student_pool'=>range(36,85),   'prop_pool'=>range(34,73),  'count'=>30,'start'=>'2024-02-01','created_base'=>'2024-01-25'],
    2 => ['student_pool'=>range(86,155),  'prop_pool'=>range(34,108), 'count'=>50,'start'=>'2024-09-01','created_base'=>'2024-08-10'],
    3 => ['student_pool'=>range(156,235), 'prop_pool'=>range(34,138), 'count'=>70,'start'=>'2025-02-01','created_base'=>'2025-01-20'],
];

foreach ($tenancy_sem_cfg as $sem => $bc) {
    $sql[] = "";
    $sql[] = "-- Sem $sem tenancies";
    $sp = $bc['student_pool'];
    $pp = $bc['prop_pool'];

    for ($i = 0; $i < $bc['count']; $i++) {
        $bid = $tenancy_id++;
        $si  = $sp[$i % count($sp)];
        $pi  = $pp[$i % count($pp)];

        // scenario 5 (Sem2): first 4 tenancies share prop 80 (whole_unit)
        if ($sem === 2 && $i < 4) {
            $pi = 80;
            $si = 86 + $i; // students 86,87,88,89
        }
        // scenario 1 (Sem1, idx 5): student 50 cancels
        if ($sem === 1 && $i === 5) $si = 50;
        // scenario 3 (Sem2, idx 0): completed tenancy for prop 34
        if ($sem === 2 && $i === 4) $pi = 34; // idx 4 (after group tenancy)

        $rent = $prop_rents_map[$pi] ?? 300;
        $lid  = $prop_landlord_map[$pi] ?? 236;
        $aid  = $agents[$bid % count($agents)];

        $start_ts = strtotime($bc['start']) + ($i % 28) * 86400;
        $start = date('Y-m-d', $start_ts);
        $end   = date('Y-m-d', $start_ts + (($i % 2 === 0) ? 180 : 365) * 86400);
        $dur   = ($i % 2 === 0) ? '1_semester' : '1_year';

        $bstatus = tenancy_status_pick($i + $bid);

        // forced overrides
        if ($sem === 1 && $i === 5)  $bstatus = 'cancelled_by_student';
        if ($sem === 1 && $i === 8)  $bstatus = 'rejected_by_landlord';
        if ($sem === 2 && $i === 0)  $bstatus = 'active';   // group tenancy 1
        if ($sem === 2 && $i === 1)  $bstatus = 'active';
        if ($sem === 2 && $i === 2)  $bstatus = 'active';
        if ($sem === 2 && $i === 3)  $bstatus = 'active';
        if ($sem === 2 && $i === 4)  $bstatus = 'completed'; // scenario 3

        $created = date('Y-m-d H:i:s', strtotime($bc['created_base']) + $i * 86400);

        $b = ['bid'=>$bid,'student_id'=>$si,'prop_id'=>$pi,'landlord_id'=>$lid,'agent_id'=>$aid,
              'start'=>$start,'end'=>$end,'dur'=>$dur,'rent'=>$rent,'bstatus'=>$bstatus,
              'sem'=>$sem,'created'=>$created];
        $sql[] = make_tenancy_sql($b);
        $tenancies_db[$bid] = $b;
    }
}

// ── Extra scenario tenancies ───────────────────────────────────────────────────

// Scenario 3: Sem3 active tenancy for prop 34
$bid_s3 = $tenancy_id++;
$r34 = $prop_rents_map[34] ?? 300;
$l34 = $prop_landlord_map[34] ?? 236;
$b_s3 = ['bid'=>$bid_s3,'student_id'=>200,'prop_id'=>34,'landlord_id'=>$l34,'agent_id'=>15,
         'start'=>'2025-02-05','end'=>'2025-08-05','dur'=>'1_semester','rent'=>$r34,
         'bstatus'=>'active','sem'=>3,'created'=>'2025-01-20 09:00:00'];
$sql[] = "-- Scenario 3: Sem3 active tenancy for prop 34";
$sql[] = make_tenancy_sql($b_s3);
$tenancies_db[$bid_s3] = $b_s3;

// Scenario 4: student 120 Sem2→completed, Sem3→active on different prop
$bid_s4a = $tenancy_id++;
$bid_s4b = $tenancy_id++;
$r50 = $prop_rents_map[50] ?? 300; $l50 = $prop_landlord_map[50] ?? 240;
$r65 = $prop_rents_map[65] ?? 290; $l65 = $prop_landlord_map[65] ?? 241;
$b_s4a = ['bid'=>$bid_s4a,'student_id'=>120,'prop_id'=>50,'landlord_id'=>$l50,'agent_id'=>16,
          'start'=>'2024-09-02','end'=>'2025-01-31','dur'=>'1_semester','rent'=>$r50,
          'bstatus'=>'completed','sem'=>2,'created'=>'2024-08-10 09:00:00'];
$b_s4b = ['bid'=>$bid_s4b,'student_id'=>120,'prop_id'=>65,'landlord_id'=>$l65,'agent_id'=>34,
          'start'=>'2025-02-08','end'=>'2025-08-07','dur'=>'1_semester','rent'=>$r65,
          'bstatus'=>'active','sem'=>3,'created'=>'2025-01-22 08:00:00'];
$sql[] = "-- Scenario 4: student 120 moves between properties";
$sql[] = make_tenancy_sql($b_s4a);
$sql[] = make_tenancy_sql($b_s4b);
$tenancies_db[$bid_s4a] = $b_s4a;
$tenancies_db[$bid_s4b] = $b_s4b;

// ═══════════════════════════════════════════════════════════════════════════════
// 5. AGENT VERIFICATIONS
//    NOTE: agent_verifications has UNIQUE on tenancy_id, so scenario 8
//    (two rows, one failed then one passed) must use two *different* tenancy IDs.
//    We create a dedicated "re-inspection" tenancy (same property, same student).
// ═══════════════════════════════════════════════════════════════════════════════
$sql[] = "";
$sql[] = "-- ============================================================";
$sql[] = "-- AGENT VERIFICATIONS";
$sql[] = "-- ============================================================";

$av_id = 1;
$av_trigger_statuses = ['agent_verifying','agent_verified','verification_failed','active','completed','contract_pending'];

// Scenario 8 needs a "second attempt" tenancy — let's pick tenancy bid=15 as the FAILED first attempt
// and create a new tenancy for the passed second attempt
$bid_reinspect = $tenancy_id++;
if (isset($tenancies_db[15])) {
    $b15 = $tenancies_db[15];
    $b_ri = ['bid'=>$bid_reinspect,'student_id'=>$b15['student_id'],'prop_id'=>$b15['prop_id'],
             'landlord_id'=>$b15['landlord_id'],'agent_id'=>$b15['agent_id'],
             'start'=>date('Y-m-d', strtotime($b15['start']) + 30),'end'=>$b15['end'],
             'dur'=>$b15['dur'],'rent'=>$b15['rent'],
             'bstatus'=>'active','sem'=>$b15['sem'],'created'=>date('Y-m-d H:i:s', strtotime($b15['created']) + 86400*5)];
    // Force tenancy 15 to verification_failed
    $tenancies_db[15]['bstatus'] = 'verification_failed';
    $sql[] = "-- Scenario 8: re-inspection tenancy (2nd attempt after failure on bid=15)";
    $sql[] = make_tenancy_sql($b_ri);
    $tenancies_db[$bid_reinspect] = $b_ri;
}

// Update tenancy 15 status to verification_failed (it was already inserted above; override with UPDATE)
$sql[] = "UPDATE tenancies SET status='verification_failed' WHERE id=15;";

foreach ($tenancies_db as $bid => $b) {
    if (!in_array($b['bstatus'], $av_trigger_statuses)) continue;

    $bstart = $b['start'] ?? $b['created'];

    if (in_array($b['bstatus'], ['active','completed','contract_pending'])) {
        $outcome = 'passed'; $severity = 'none';
        $submitted = q(date('Y-m-d H:i:s', strtotime($bstart) - 10 * 86400));
        $prop_match = 1;
    } elseif ($b['bstatus'] === 'agent_verified') {
        $outcome = 'passed'; $severity = 'none';
        $submitted = q(date('Y-m-d H:i:s', strtotime($bstart) - 8 * 86400));
        $prop_match = 1;
    } elseif ($b['bstatus'] === 'verification_failed') {
        $outcome = 'failed'; $severity = 'major';
        $submitted = q(date('Y-m-d H:i:s', strtotime($bstart) - 5 * 86400));
        $prop_match = 0;
    } else { // agent_verifying
        $outcome = 'in_progress'; $severity = 'none';
        $submitted = 'NULL';
        $prop_match = null; // not yet assessed
    }

    $started  = date('Y-m-d H:i:s', strtotime($b['created']) + 86400 * 2);
    $deadline = date('Y-m-d H:i:s', strtotime($started) + 86400 * 7);

    $notes  = ($outcome === 'failed' || $outcome === 'in_progress') ? 'Inspection ongoing or issues found.' : 'Property inspected. Condition matches listing.';
    $issues = ($outcome === 'failed') ? 'AC unit not working. Bathroom mold issues.' : 'None';

    // scenario 8: bid=15 is first fail, $bid_reinspect is second pass
    if ($bid === 15) {
        $notes  = 'First inspection: property has significant issues. AC not functional, bathroom mold.';
        $issues = 'AC not working, mold in bathroom, ceiling water stain';
    }

    $pm_sql = $prop_match === null ? 'NULL' : qi($prop_match);

    $sql[] = "INSERT IGNORE INTO agent_verifications (id,tenancy_id,agent_id,started_at,submitted_at,deadline_at,property_matches_listing,property_address_correct,facilities_match,landlord_id_matches,ownership_doc_sighted,inspection_notes,issues_found,issue_severity,outcome,student_proceeded_with_disclosure,student_decision_at) VALUES "
        . "({$av_id},{$bid},{$b['agent_id']}," . q($started) . "," . $submitted . "," . q($deadline) . ","
        . $pm_sql . ",1," . $pm_sql . ",1,1,"
        . q($notes) . "," . q($issues) . "," . q($severity) . "," . q($outcome) . ",NULL,NULL);";
    $av_id++;
}

// ═══════════════════════════════════════════════════════════════════════════════
// 6. CONTRACTS
// ═══════════════════════════════════════════════════════════════════════════════
$sql[] = "";
$sql[] = "-- ============================================================";
$sql[] = "-- CONTRACTS";
$sql[] = "-- ============================================================";

$contract_id = 1;
$contract_terms = 'Standard tenancy agreement. Monthly rent payable on the 1st of each month. Security deposit non-refundable for early termination without cause. Property to be maintained in good condition throughout tenancy.';

foreach ($tenancies_db as $bid => $b) {
    if (!in_array($b['bstatus'], ['active','completed','contract_pending'])) continue;

    $code   = 'RB-' . strtoupper(substr(md5('contract'.$bid), 0, 8));
    $signed = date('Y-m-d H:i:s', strtotime($b['start']) - 7 * 86400);
    $cstatus = ($b['bstatus'] === 'completed') ? 'completed' : (($b['bstatus'] === 'contract_pending') ? 'pending_signatures' : 'active');

    $stu_signed_at  = ($cstatus !== 'pending_signatures') ? q($signed) : 'NULL';
    $lld_signed_at  = ($cstatus !== 'pending_signatures') ? q(date('Y-m-d H:i:s', strtotime($signed) + 3600))    : 'NULL';
    $agt_signed_at  = ($cstatus !== 'pending_signatures') ? q(date('Y-m-d H:i:s', strtotime($signed) + 7200))    : 'NULL';
    $activated_at   = in_array($cstatus, ['active','completed'])
        ? q(date('Y-m-d H:i:s', strtotime($signed) + 7200)) : 'NULL';

    $sql[] = "INSERT IGNORE INTO contracts (id,contract_code,tenancy_id,student_id,landlord_id,agent_id,property_id,start_date,end_date,monthly_rent,deposit,terms,student_signed_at,landlord_signed_at,agent_signed_at,generated_at,generated_by,status,activated_at,created_at) VALUES "
        . "({$contract_id}," . q($code) . ",{$bid},{$b['student_id']},{$b['landlord_id']},{$b['agent_id']},{$b['prop_id']},"
        . q($b['start']) . ',' . q($b['end']) . ','
        . qf($b['rent']) . ',' . qf($b['rent']*2) . ','
        . q($contract_terms) . ','
        . $stu_signed_at . ',' . $lld_signed_at . ',' . $agt_signed_at . ','
        . q(date('Y-m-d H:i:s', strtotime($b['created']) + 86400)) . ','
        . qi($b['agent_id']) . ','
        . q($cstatus) . ',' . $activated_at . ','
        . q($b['created']) . ");";
    $contract_id++;
}

// ═══════════════════════════════════════════════════════════════════════════════
// 7. REPORTS
// ═══════════════════════════════════════════════════════════════════════════════
$sql[] = "";
$sql[] = "-- ============================================================";
$sql[] = "-- REPORTS (~15 rows)";
$sql[] = "-- ============================================================";

$reports = [
    [1,  45,  238, 'general', null, 'scam',           'Landlord listed a property that does not exist. Sent fake photos and asked for deposit upfront.',           'pending',  '2024-03-15 10:00:00', null,                   null],
    [2,  50,  62,  'message', 1,    'harassment',     'This student sent threatening messages after I declined to share a house.',                                 'pending',  '2024-04-10 14:30:00', null,                   null],
    [3,  72,  15,  'general', null, 'misconduct',     'Agent was unprofessional during inspection and demanded extra payment for the inspection report.',           'reviewed', '2024-05-20 09:00:00', '2024-06-01 11:00:00', 17],
    [4,  88,  240, 'tenancy', 3,    'fake_information','Listing photos show a fully furnished room but actual property has almost no furniture.',                   'actioned', '2024-10-05 16:00:00', '2024-10-20 10:00:00', 17],
    [5,  95,  244, 'tenancy', 8,    'fraud',          'Landlord collected RM600 deposit but property was already rented to someone else. Not responding to calls.','actioned', '2024-11-12 08:30:00', '2024-11-25 15:00:00', 17],
    [6,  110, 250, 'general', null, 'other',          'Minor dispute over maintenance responsibility. Both parties later resolved it amicably.',                    'dismissed','2025-01-08 12:00:00', '2025-01-15 09:00:00', 17],
    [7,  130, 255, 'general', null, 'scam',           'Landlord asking for 3 months deposit before viewing. Refused to show property first.',                      'pending',  '2025-02-14 10:00:00', null,                   null],
    [8,  140, 58,  'message', 5,    'harassment',     'Repeated unwanted messages and threats after rental dispute.',                                              'pending',  '2025-03-01 11:00:00', null,                   null],
    [9,  160, 262, 'tenancy', 12,   'fake_information','Room size stated as 12sqm but actual size is less than 8sqm. Ceiling leaks badly.',                        'reviewed', '2025-03-20 14:00:00', '2025-04-02 10:00:00', 16],
    [10, 170, 258, 'general', null, 'fraud',          'Same property listed under multiple accounts at different prices.',                                         'pending',  '2025-04-05 09:30:00', null,                   null],
    [11, 180, 16,  'general', null, 'misconduct',     'Agent contacted landlord directly to negotiate a deal without going through the platform.',                  'reviewed', '2025-04-18 13:00:00', '2025-05-01 10:00:00', 17],
    [12, 190, 46,  'message', 9,    'other',          'Student filed complaint about late reply but misunderstood the response time policy.',                       'dismissed','2025-05-10 10:00:00', '2025-05-12 09:00:00', 16],
    [13, 200, 252, 'general', null, 'scam',           'Property listing copy-pasted from another platform. Photos belong to a different location entirely.',       'actioned', '2025-05-20 11:00:00', '2025-06-01 15:00:00', 17],
    [14, 210, 75,  'message', 15,   'harassment',     'Landlord keeps calling after student cancelled tenancy. Very aggressive behavior.',                         'pending',  '2025-06-01 14:00:00', null,                   null],
    [15, 220, 265, 'tenancy', 20,   'fake_information','WiFi speed stated as 100Mbps but actual speed never exceeds 5Mbps. Router shared with 20+ tenants.',       'pending',  '2025-06-10 09:00:00', null,                   null],
];

foreach ($reports as $r) {
    [$rid,$reporter,$reported,$ctx_type,$ctx_id,$reason,$details,$status,$created,$reviewed_at,$reviewed_by] = $r;
    $sql[] = "INSERT IGNORE INTO reports (id,reporter_id,reported_user_id,context_type,context_id,reason,details,status,created_at,reviewed_at,reviewed_by) VALUES "
        . "({$rid},{$reporter},{$reported}," . q($ctx_type) . ","
        . qi($ctx_id) . "," . q($reason) . "," . q($details) . "," . q($status) . ","
        . q($created) . "," . ($reviewed_at ? q($reviewed_at) : 'NULL') . "," . ($reviewed_by ? qi($reviewed_by) : 'NULL') . ");";
}

$sql[] = "";
$sql[] = "SET FOREIGN_KEY_CHECKS=1;";
$sql[] = "";
$sql[] = "-- Seed complete. Check counts:";
$sql[] = "-- SELECT 'users' tbl, COUNT(*) n FROM users UNION ALL SELECT 'properties',COUNT(*) FROM properties UNION ALL SELECT 'tenancies',COUNT(*) FROM tenancies UNION ALL SELECT 'reports',COUNT(*) FROM reports;";

echo implode("\n", $sql) . "\n";
