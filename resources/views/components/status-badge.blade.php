@props(['status'])

<x-badge :tone="$status->tone()">{{ $status->label() }}</x-badge>
