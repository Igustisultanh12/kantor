package id.my.sisinden.app.core.realtime

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.FlowPreview
import kotlinx.coroutines.flow.debounce
import kotlinx.coroutines.flow.filter
import kotlinx.coroutines.launch

/**
 * Muat ulang data ViewModel saat server menyiarkan perubahan pada [modules]
 * (kosong = modul apa pun kecuali "general"). Beberapa sinyal beruntun
 * digabung jadi satu muat ulang.
 */
@OptIn(FlowPreview::class)
fun ViewModel.onRealtime(vararg modules: String, refresh: () -> Unit) {
    val wanted = modules.toSet()
    viewModelScope.launch {
        RealtimeClient.changes
            .filter { changed ->
                if (wanted.isEmpty()) changed.any { it != "general" } else changed.any { it in wanted || it == "*" }
            }
            .debounce(1_200)
            .collect { refresh() }
    }
}
