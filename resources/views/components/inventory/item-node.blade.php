@props(['item'])

@php($aEnfants = $item->descendants->isNotEmpty())

<li style="margin:.15rem 0;" x-data="{ ouvert: true, survol: false }"
    @expand-all.window="ouvert = true"
    @collapse-all.window="ouvert = false">
    <div wire:key="item-{{ $item->id }}"
         draggable="true"
         @dragstart="draggedId = {{ $item->id }}; $event.dataTransfer.effectAllowed = 'move'"
         @dragend="draggedId = null"
         @if ($item->is_container)
             @dragover.prevent="survol = (draggedId && draggedId !== {{ $item->id }})"
             @dragleave="survol = false"
             @drop.prevent.stop="survol = false; if (draggedId && draggedId !== {{ $item->id }}) $wire.deplacerVers(draggedId, 'item:{{ $item->id }}')"
         @endif
         :style="survol
            ? 'padding:.3rem .5rem;background:#e8f0fe;border:1px solid #3584e4;border-radius:6px;display:flex;align-items:center;gap:.4rem;cursor:grab;'
            : 'padding:.3rem .5rem;background:#fff;border:1px solid #e8e8e8;border-radius:6px;display:flex;align-items:center;gap:.4rem;cursor:grab;'">
        @if ($aEnfants)
            <button type="button" @click="ouvert = !ouvert"
                    title="Déplier / replier"
                    style="border:none;background:transparent;cursor:pointer;width:1rem;padding:0;color:#5e5c64;font-size:.8rem;"
                    x-text="ouvert ? '▾' : '▸'"></button>
        @else
            <span style="width:1rem;display:inline-block;"></span>
        @endif
        <span>{{ $item->is_container ? '📦' : '•' }}</span>
        <span style="font-weight:{{ $item->is_container ? '600' : '400' }};">{{ $item->name }}</span>

        @if (!is_null($item->quantity))
            <span style="color:#5e5c64;font-size:.85rem;">×{{ rtrim(rtrim((string) $item->quantity, '0'), '.') }}{{ $item->unit ? ' '.$item->unit : '' }}</span>
        @endif

        @foreach ($item->tags as $tag)
            <span style="background:#e8f0fe;color:#1a73e8;border-radius:10px;padding:0 .5rem;font-size:.75rem;">{{ $tag->name }}</span>
        @endforeach

        <span style="margin-left:auto;display:flex;gap:.25rem;">
            @if ($item->is_container)
                <button type="button" title="Ajouter un objet ici"
                        wire:click="$dispatch('item-creer', { houseId: {{ $item->house_id }}, parentId: {{ $item->id }} })"
                        style="border:none;background:transparent;cursor:pointer;font-size:.9rem;">➕</button>
            @endif
            <button type="button" title="Modifier"
                    wire:click="$dispatch('item-editer', { itemId: {{ $item->id }} })"
                    style="border:none;background:transparent;cursor:pointer;font-size:.9rem;">✏️</button>
            <button type="button" title="Déplacer"
                    wire:click="$dispatch('item-deplacer', { itemId: {{ $item->id }} })"
                    style="border:none;background:transparent;cursor:pointer;font-size:.9rem;">↪️</button>
            <button type="button" title="Supprimer"
                    wire:click="supprimer({{ $item->id }})"
                    wire:confirm="Supprimer « {{ $item->name }} » et tout son contenu ?"
                    style="border:none;background:transparent;cursor:pointer;font-size:.9rem;">🗑️</button>
        </span>
    </div>

    @if ($aEnfants)
        <ul x-show="ouvert" x-cloak
            style="list-style:none;padding-left:1.25rem;margin:.15rem 0;border-left:1px dashed #d0d0d0;">
            @foreach ($item->descendants->sortBy([['is_container', 'asc'], ['name', 'asc']]) as $enfant)
                <x-inventory.item-node :item="$enfant" />
            @endforeach
        </ul>
    @endif
</li>
