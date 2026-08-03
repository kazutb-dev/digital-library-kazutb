@php($scannerId = 'camera-scanner-'.preg_replace('/[^a-z0-9_-]/i','-',$targetId))
<div id="{{ $scannerId }}" class="mt-2" data-camera-scanner data-target="{{ $targetId }}">
    <button type="button" class="admin-btn admin-btn-secondary text-xs" data-camera-start><span class="material-symbols-outlined text-[18px]">photo_camera</span>{{ __('librarian.scanner.start_camera') }}</button>
    <div class="mt-2 hidden rounded-xl border border-slate-200 bg-slate-950 p-3" data-camera-panel>
        <div class="flex items-center justify-between gap-3"><span class="flex items-center gap-2 text-xs font-semibold text-white"><i class="h-2 w-2 animate-pulse rounded-full bg-red-500"></i>{{ __('librarian.scanner.active') }}</span><button type="button" class="text-xs font-semibold text-white underline" data-camera-stop>{{ __('librarian.scanner.stop') }}</button></div>
        <video class="mt-2 max-h-64 w-full rounded-lg object-cover" playsinline muted data-camera-video></video>
        <p class="mt-2 text-xs text-slate-300" data-camera-status aria-live="polite"></p>
    </div>
</div>
<script>
(()=>{const root=document.getElementById(@json($scannerId));if(!root||root.dataset.ready)return;root.dataset.ready='1';const target=document.getElementById(root.dataset.target),panel=root.querySelector('[data-camera-panel]'),video=root.querySelector('video'),status=root.querySelector('[data-camera-status]');let stream=null,active=false,detector=null;
const stop=()=>{active=false;if(stream){stream.getTracks().forEach(track=>track.stop());stream=null}video.srcObject=null;panel.classList.add('hidden')};
root.querySelector('[data-camera-stop]').addEventListener('click',stop);window.addEventListener('pagehide',stop);
root.querySelector('[data-camera-start]').addEventListener('click',async()=>{if(!navigator.mediaDevices?.getUserMedia){status.textContent=@json(__('librarian.scanner.unsupported'));panel.classList.remove('hidden');return}try{stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'}},audio:false});panel.classList.remove('hidden');video.srcObject=stream;await video.play();active=true;if(!('BarcodeDetector'in window)){status.textContent=@json(__('librarian.scanner.fallback'));return}detector=new BarcodeDetector({formats:['qr_code','code_128']});status.textContent=@json(__('librarian.scanner.aim'));const loop=async()=>{if(!active)return;try{const codes=await detector.detect(video);if(codes[0]?.rawValue){target.value=codes[0].rawValue;target.dispatchEvent(new Event('input',{bubbles:true}));target.dispatchEvent(new Event('change',{bubbles:true}));stop();target.focus();return}}catch(e){}requestAnimationFrame(loop)};requestAnimationFrame(loop)}catch(e){status.textContent=@json(__('librarian.scanner.denied'));panel.classList.remove('hidden')}});
})();
</script>
