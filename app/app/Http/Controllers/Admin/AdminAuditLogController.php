<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminAuditLogController extends Controller
{
    public function index(Request $request)
    {
        $isDate = static fn (string $v): bool => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $v);

        $q = trim((string)$request->input('q', ''));
        $actor = (string)$request->input('actor_user_id', '');
        $action = (string)$request->input('action', '');
        $entityType = (string)$request->input('entity_type', '');
        $entityId = (string)$request->input('entity_id', '');
        $hasMemo = (string)$request->input('has_memo', '');
        $month = (string)$request->input('month', '');
        $createdFrom = (string)$request->input('created_from', '');
        $createdTo = (string)$request->input('created_to', '');

        $query = DB::table('audit_logs as al')
            ->leftJoin('users as actor', 'actor.id', '=', 'al.actor_user_id')
            ->select('al.*')
            ->addSelect('actor.email as actor_email')
            ->selectSub(
                DB::table('account_user as au')
                    ->join('accounts as a', 'a.id', '=', 'au.account_id')
                    ->join('users as u', 'u.id', '=', 'au.user_id')
                    ->whereColumn('au.user_id', 'al.actor_user_id')
                    ->selectRaw("coalesce(nullif(a.internal_name, ''), u.name)")
                    ->orderBy('au.account_id')
                    ->limit(1),
                'actor_account_display_name'
            )
            ->selectSub(
                DB::table('account_user as au')
                    ->join('accounts as a', 'a.id', '=', 'au.account_id')
                    ->whereColumn('au.user_id', 'al.actor_user_id')
                    ->select('a.assignee_name')
                    ->orderBy('au.account_id')
                    ->limit(1),
                'actor_assignee_name'
            );
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->whereRaw('cast(al.id as text) ilike ?', ["%{$q}%"])
                    ->orWhereRaw('cast(al.actor_user_id as text) ilike ?', ["%{$q}%"])
                    ->orWhere('al.action', 'ilike', "%{$q}%")
                    ->orWhere('al.entity_type', 'ilike', "%{$q}%")
                    ->orWhereRaw('cast(al.entity_id as text) ilike ?', ["%{$q}%"])
                    ->orWhere('actor.email', 'ilike', "%{$q}%")
                    ->orWhere('actor.name', 'ilike', "%{$q}%")
                    ->orWhereExists(function ($sq) use ($q) {
                        $sq->selectRaw('1')
                            ->from('account_user as au')
                            ->join('users as u', 'u.id', '=', 'au.user_id')
                            ->leftJoin('accounts as a', 'a.id', '=', 'au.account_id')
                            ->whereColumn('au.user_id', 'al.actor_user_id')
                            ->where(function ($userSub) use ($q) {
                                $userSub->where('u.name', 'ilike', "%{$q}%")
                                    ->orWhere('u.email', 'ilike', "%{$q}%")
                                    ->orWhere('a.internal_name', 'ilike', "%{$q}%")
                                    ->orWhere('a.assignee_name', 'ilike', "%{$q}%");
                            });
                    });
            });
        }
        if ($actor !== '') {
            $query->where('al.actor_user_id', (int)$actor);
        }
        if ($action !== '') {
            $query->where('al.action', $action);
        }
        if ($entityType !== '') {
            $query->where('al.entity_type', $entityType);
        }
        if ($entityId !== '' && is_numeric($entityId)) {
            $query->where('al.entity_id', (int)$entityId);
        }
        if ($hasMemo === 'with') {
            $query->whereNotNull('al.memo')->where('al.memo', '<>', '');
        } elseif ($hasMemo === 'without') {
            $query->where(function ($sub) {
                $sub->whereNull('al.memo')->orWhere('al.memo', '');
            });
        }
        if ($month !== '') {
            $query->whereRaw("to_char(al.created_at, 'YYYY-MM') = ?", [$month]);
        }
        if ($createdFrom !== '' && $isDate($createdFrom)) {
            $query->whereDate('al.created_at', '>=', $createdFrom);
        }
        if ($createdTo !== '' && $isDate($createdTo)) {
            $query->whereDate('al.created_at', '<=', $createdTo);
        }

        $logs = $query->orderBy('al.id', 'desc')->limit(300)->get();

        $actorOptions = DB::table('audit_logs')
            ->select('actor_user_id')
            ->whereNotNull('actor_user_id')
            ->distinct()
            ->orderBy('actor_user_id')
            ->pluck('actor_user_id')
            ->all();

        $actionOptions = DB::table('audit_logs')
            ->select('action')
            ->whereNotNull('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->all();

        $entityTypeOptions = DB::table('audit_logs')
            ->select('entity_type')
            ->whereNotNull('entity_type')
            ->distinct()
            ->orderBy('entity_type')
            ->pluck('entity_type')
            ->all();

        $monthOptions = DB::table('audit_logs')
            ->selectRaw("to_char(created_at, 'YYYY-MM') as ym")
            ->distinct()
            ->orderBy('ym', 'desc')
            ->pluck('ym')
            ->all();

        return view('admin.audit-logs.index', [
            'logs' => $logs,
            'filters' => [
                'q' => $q,
                'actor_user_id' => $actor,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'has_memo' => $hasMemo,
                'month' => $month,
                'created_from' => $createdFrom,
                'created_to' => $createdTo,
            ],
            'actorOptions' => $actorOptions,
            'actionOptions' => $actionOptions,
            'entityTypeOptions' => $entityTypeOptions,
            'monthOptions' => $monthOptions,
            'presenceOptions' => [
                'with' => 'あり',
                'without' => 'なし',
            ],
        ]);
    }
}
