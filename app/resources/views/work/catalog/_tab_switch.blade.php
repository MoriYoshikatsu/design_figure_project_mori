<div class="catalog-segment" data-catalog-tab-switch>
    <button
        type="button"
        class="catalog-segment-btn @if($activeTab === 'skus') is-active @endif"
        data-catalog-tab="skus"
        data-catalog-tab-url="{{ $skuTabUrl }}"
        @if(!$canAccessSkus) disabled @endif
    >
        <span>SKU管理</span>
        <span class="catalog-segment-count">{{ $skuCount }}</span>
        @if(!$canAccessSkus)
            <span class="catalog-segment-note">権限なし</span>
        @endif
    </button>

    <button
        type="button"
        class="catalog-segment-btn @if($activeTab === 'price_books') is-active @endif"
        data-catalog-tab="price_books"
        data-catalog-tab-url="{{ $priceBookTabUrl }}"
        @if(!$canAccessPriceBooks) disabled @endif
    >
        <span>パーツ価格表</span>
        <span class="catalog-segment-count">{{ $priceBookCount }}</span>
        @if(!$canAccessPriceBooks)
            <span class="catalog-segment-note">権限なし</span>
        @endif
    </button>
</div>
