<div id="catalog-detail-backdrop" class="catalog-detail-backdrop" hidden></div>
<aside id="catalog-detail-drawer" class="catalog-detail-drawer" hidden>
    <div class="catalog-detail-header">
        <div>
            <strong id="catalog-detail-title">詳細</strong>
            <div id="catalog-detail-subtitle" class="muted"></div>
        </div>
        <button type="button" id="catalog-detail-close">閉じる</button>
    </div>

    <div id="catalog-detail-status" class="catalog-detail-status"></div>

    <table class="catalog-detail-table">
        <tbody id="catalog-detail-body"></tbody>
    </table>

    <div id="catalog-detail-links" class="actions" style="margin-top:12px;"></div>

    <form id="catalog-detail-delete-form" method="POST" style="margin-top:12px;" hidden>
        @csrf
        <input type="hidden" name="_mode" value="submit">
        <input type="hidden" name="tab" value="{{ $activeTab }}" class="catalog-active-tab-input">
        <button type="submit" id="catalog-detail-delete-button">削除申請</button>
    </form>
</aside>
