<button {{ $attributes->merge(['type' => 'submit', 'class' => 'rounded-full bg-blue-900 px-5 py-2.5 text-sm font-bold text-white hover:bg-blue-950 focus:ring-2 focus:ring-amber-400 focus:ring-offset-2']) }}>
    {{ $slot }}
</button>
